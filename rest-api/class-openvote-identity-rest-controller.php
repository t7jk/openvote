<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST: głosowanie bez logowania (kod e-mail).
 * GET /identity/config, GET /identity/status, POST /identity/request-code, POST /identity/verify-code.
 */
class Openvote_Identity_Rest_Controller {

    private const NAMESPACE = 'openvote/v1';

    /**
     * Czy żądanie dotyczy tras OpenVote (nie samego indeksu przestrzeni nazw).
     */
    private static function is_openvote_route( $request ): bool {
        if ( ! $request instanceof WP_REST_Request ) {
            return false;
        }
        return 0 === strpos( strtolower( (string) $request->get_route() ), '/openvote/v1/' );
    }

    /**
     * REST API Guard: nie blokuj anonimowych żądań do tras OpenVote.
     * Wyłączenie: add_filter( 'openvote_allow_anonymous_rest', '__return_false' ).
     *
     * @param mixed           $prevent
     * @param WP_REST_Request $request
     * @return mixed
     */
    public function filter_rest_api_guard_prevent( $prevent, $request = null ) {
        if ( self::is_openvote_route( $request ) && apply_filters( 'openvote_allow_anonymous_rest', true ) ) {
            return false;
        }
        return $prevent;
    }

    /**
     * REST API Guard: gdy administrator używa listy dozwolonych tras, dopisz trasy OpenVote.
     * Pusta lista oznacza „brak listy” — wtedy niczego nie dodajemy (inaczej zablokowalibyśmy resztę API).
     *
     * @param mixed $allowlist
     * @return mixed
     */
    public function filter_rest_api_guard_allowlist( $allowlist ) {
        if ( empty( $allowlist ) || ! apply_filters( 'openvote_allow_anonymous_rest', true ) ) {
            return $allowlist;
        }
        if ( ! is_array( $allowlist ) ) {
            $allowlist = preg_split( '/\r\n|\r|\n/', (string) $allowlist );
        }
        $allowlist[] = '/openvote/v1/'; // REST API Guard: dopasowanie podciągu (preg_quote, bez kotwic).
        return $allowlist;
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/identity/config', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_config' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NAMESPACE, '/identity/status', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_status' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NAMESPACE, '/identity/request-code', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [ $this, 'request_code' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NAMESPACE, '/identity/verify-code', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [ $this, 'verify_code' ], 'permission_callback' => '__return_true',
        ] );
    }

    private function respond( array $data, int $status = 200 ): WP_REST_Response {
        Openvote_Identity_Verifier::send_nocache();
        $r = new WP_REST_Response( $data, $status );
        $r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        return $r;
    }

    private function error( string $code, string $msg, int $status ): WP_Error {
        Openvote_Identity_Verifier::send_nocache();
        return new WP_Error( $code, $msg, [ 'status' => $status ] );
    }

    private function urls(): array {
        return [ 'login_url' => wp_login_url(), 'lost_password_url' => wp_lostpassword_url() ];
    }

    private function body( WP_REST_Request $request ): callable {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = $request->get_body_params();
        }
        return fn( string $k ) => isset( $body[ $k ] ) && is_scalar( $body[ $k ] ) ? (string) $body[ $k ] : '';
    }

    public function get_config( WP_REST_Request $request ): WP_REST_Response {
        return $this->respond( array_merge( [
            'enabled'        => Openvote_Identity_Verifier::is_enabled(),
            'max_attempts'   => Openvote_Identity_Verifier::MAX_ATTEMPTS,
            'code_length'    => Openvote_Identity_Verifier::CODE_LENGTH,
            'resend_seconds' => Openvote_Identity_Verifier::resend_seconds(),
            'code_ttl'       => Openvote_Identity_Verifier::CODE_TTL,
        ], $this->urls() ) );
    }

    public function get_status( WP_REST_Request $request ): WP_REST_Response {
        return $this->respond( [
            'locked'        => Openvote_Identity_Verifier::is_locked(),
            'attempts_left' => Openvote_Identity_Verifier::attempts_left(),
            'max_attempts'  => Openvote_Identity_Verifier::MAX_ATTEMPTS,
        ] );
    }

    public function request_code( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Openvote_Identity_Verifier::is_enabled() ) {
            return $this->error( 'identity_disabled', __( 'Głosowanie bez logowania jest wyłączone.', 'openvote' ), 403 );
        }
        $get  = $this->body( $request );
        $type = sanitize_key( $get( 'target_type' ) );
        $id   = absint( $get( 'target_id' ) );
        if ( ! in_array( $type, [ 'poll', 'survey' ], true ) || $id <= 0 ) {
            return $this->error( 'invalid_target', __( 'Nieprawidłowy cel.', 'openvote' ), 400 );
        }
        $target = Openvote_Identity_Verifier::get_target( $type, $id );
        if ( is_wp_error( $target ) ) {
            Openvote_Identity_Verifier::send_nocache();
            return $target;
        }
        $email = trim( sanitize_text_field( $get( 'email' ) ) );
        if ( $email === '' || mb_strlen( $email ) > 254 || ! is_email( $email ) ) {
            return $this->error( 'identity_missing_fields', __( 'Podaj poprawny adres e-mail.', 'openvote' ), 400 );
        }

        $max = Openvote_Identity_Verifier::MAX_ATTEMPTS;
        if ( Openvote_Identity_Verifier::is_locked() ) {
            return $this->respond( array_merge( [
                'status' => 'locked', 'attempts_left' => 0, 'locked' => true,
                'message' => Openvote_Identity_Verifier::locked_message(),
            ], $this->urls() ) );
        }

        $uid = Openvote_Identity_Verifier::find_user_by_email( $email );
        if ( $uid <= 0 ) {
            Openvote_Identity_Verifier::record_failed_lookup();
            $left = Openvote_Identity_Verifier::attempts_left();
            if ( $left <= 0 ) {
                return $this->respond( array_merge( [
                    'status' => 'locked', 'attempts_left' => 0, 'locked' => true,
                    'message' => Openvote_Identity_Verifier::locked_message(),
                ], $this->urls() ) );
            }
            return $this->respond( [
                'status' => 'not_found', 'attempts_left' => $left, 'locked' => false,
                'message' => sprintf(
                    /* translators: 1: attempts left, 2: max attempts */
                    __( 'Nie znaleźliśmy konta z tym adresem. Sprawdź pisownię (pozostało prób: %1$d z %2$d).', 'openvote' ),
                    $left,
                    Openvote_Identity_Verifier::MAX_ATTEMPTS
                ),
            ] );
        }

        $cool = Openvote_Identity_Verifier::cooldown_left( $uid );
        if ( $cool > 0 ) {
            return $this->respond( [
                'status' => 'cooldown', 'resend_in' => $cool,
                'message' => sprintf( __( 'Kolejny kod możesz wysłać za %d s.', 'openvote' ), $cool ),
            ] );
        }
        if ( Openvote_Identity_Verifier::user_code_limit_reached( $uid ) || Openvote_Identity_Verifier::ip_mail_limit_reached() ) {
            return $this->error( 'identity_throttled', __( 'Wysłano zbyt wiele kodów. Spróbuj ponownie za jakiś czas lub zaloguj się.', 'openvote' ), 429 );
        }

        $sent = Openvote_Identity_Verifier::issue_and_send_code( $uid );
        if ( is_wp_error( $sent ) ) {
            return $this->error( 'code_send_failed', __( 'Nie udało się wysłać e-maila. Spróbuj ponownie za chwilę lub zaloguj się.', 'openvote' ), 500 );
        }
        return $this->respond( [
            'status'        => 'code_sent',
            'resend_in'     => Openvote_Identity_Verifier::resend_seconds(),
            'attempts_left' => Openvote_Identity_Verifier::attempts_left(),
            'message'       => __( 'Wysłaliśmy 4-cyfrowy kod na podany adres. Wpisz go poniżej. Nie widzisz wiadomości? Sprawdź folder SPAM.', 'openvote' ),
        ] );
    }

    /**
     * Zamaskowane etykiety dla wyboru „jawnie / anonimowo” (te same dane, co w wynikach jawnych głosowań).
     */
    private function vote_labels( WP_User $user ): array {
        $first = Openvote_Field_Map::get_user_value( $user, 'first_name' );
        $last  = Openvote_Field_Map::get_user_value( $user, 'last_name' );
        $city  = Openvote_Field_Map::get_user_value( $user, 'city' );
        $email = Openvote_Field_Map::get_user_value( $user, 'email' );
        $nick  = Openvote_Field_Map::get_user_value( $user, 'nickname' );
        $name  = trim( $first . ' ' . $last );
        $open  = sprintf(
            /* translators: 1: full name, 2: city, 3: masked email */
            __( 'Jawnie — w wynikach pojawi się: %1$s (%2$s), %3$s.', 'openvote' ),
            $name !== '' ? $name : '—',
            $city !== '' ? $city : '—',
            $email !== '' ? Openvote_Vote::anonymize_email( $email ) : '…'
        );
        $anon = $nick !== ''
            ? sprintf( __( 'Anonimowo — w wynikach pojawi się tylko pseudonim: %s.', 'openvote' ), $nick )
            : __( 'Anonimowo — w wynikach pojawi się tylko Twój pseudonim, bez imienia i nazwiska.', 'openvote' );
        return [ 'open' => $open, 'anon' => $anon ];
    }

    public function verify_code( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Openvote_Identity_Verifier::is_enabled() ) {
            return $this->error( 'identity_disabled', __( 'Głosowanie bez logowania jest wyłączone.', 'openvote' ), 403 );
        }
        $get  = $this->body( $request );
        $type = sanitize_key( $get( 'target_type' ) );
        $id   = absint( $get( 'target_id' ) );
        if ( ! in_array( $type, [ 'poll', 'survey' ], true ) || $id <= 0 ) {
            return $this->error( 'invalid_target', __( 'Nieprawidłowy cel.', 'openvote' ), 400 );
        }
        $target = Openvote_Identity_Verifier::get_target( $type, $id );
        if ( is_wp_error( $target ) ) {
            Openvote_Identity_Verifier::send_nocache();
            return $target;
        }
        $email = trim( sanitize_text_field( $get( 'email' ) ) );
        $code  = trim( $get( 'code' ) );
        if ( $email === '' || ! is_email( $email ) || ! preg_match( '/^\d{4}$/', $code ) ) {
            return $this->error( 'identity_missing_fields', __( 'Podaj adres e-mail i 4-cyfrowy kod.', 'openvote' ), 400 );
        }

        $expired = fn() => $this->respond( [
            'verified' => false, 'status' => 'code_expired',
            'message'  => __( 'Kod wygasł lub wykorzystano limit prób — wyślij nowy kod.', 'openvote' ),
        ] );

        // Nieznany e-mail wygląda tak samo jak brak ważnego kodu (brak wycieku istnienia konta).
        $uid = Openvote_Identity_Verifier::find_user_by_email( $email );
        if ( $uid <= 0 ) {
            return $expired();
        }
        $left   = 0;
        $result = Openvote_Identity_Verifier::check_code( $uid, $code, $left );
        if ( $result === 'expired' ) {
            return $expired();
        }
        if ( $result === 'wrong' ) {
            return $this->respond( [
                'verified' => false, 'status' => 'wrong_code', 'code_attempts_left' => $left,
                'message'  => sprintf( __( 'Nieprawidłowy kod. Pozostało prób: %1$d z %2$d.', 'openvote' ), $left, Openvote_Identity_Verifier::MAX_CODE_ATTEMPTS ),
            ] );
        }

        $user  = get_userdata( $uid );
        $token = Openvote_Identity_Verifier::issue_token( $uid );
        $out   = [
            'verified'     => true,
            'token'        => $token,
            'expires_in'   => Openvote_Identity_Verifier::TOKEN_TTL,
            'display_name' => (string) $user->display_name,
            'labels'       => $this->vote_labels( $user ),
        ];

        Openvote_Identity_Verifier::authorize_for_request( $uid );
        if ( $type === 'poll' ) {
            $has_voted = Openvote_Vote::has_voted( $id, $uid );
            $err       = null;
            if ( ! $has_voted ) {
                $c = Openvote_Eligibility::can_vote( $uid, $id );
                if ( ! $c['eligible'] ) {
                    $err = $c['reason'];
                }
            }
            $out['poll'] = [ 'has_voted' => $has_voted, 'eligible_error' => $err ];
        } else {
            $resp = Openvote_Survey::get_user_response( $id, $uid );
            $prof = Openvote_Survey::check_user_profile( $uid );
            $out['survey'] = [
                'my_response'   => $resp ? [ 'status' => $resp->response_status, 'answers' => $resp->answers ] : null,
                'profile_error' => $prof['ok'] ? null : sprintf(
                    __( 'Twój profil jest niekompletny (brakuje: %s). Zaloguj się, aby uzupełnić profil, a potem wypełnij ankietę.', 'openvote' ),
                    implode( ', ', $prof['missing'] )
                ),
            ];
        }
        return $this->respond( $out );
    }
}
