<?php
defined( 'ABSPATH' ) || exit;

/**
 * Głosowanie bez logowania: potwierdzenie posiadania skrzynki e-mail 4-cyfrowym kodem.
 * Limity, kody (przechowywane tylko jako HMAC), tokeny.
 */
class Openvote_Identity_Verifier {

    const OPTION_ENABLED = 'openvote_identity_voting_enabled';
    const HEADER         = 'X-Openvote-Identity';

    /** Nieudane wyszukania e-mail na IP (wszystkie cele) w oknie 24 h. */
    const MAX_ATTEMPTS        = 5;
    const LOOKUP_WINDOW       = DAY_IN_SECONDS;
    const CODE_LENGTH         = 4;
    const CODE_TTL            = 900;
    const RESEND_SECONDS      = 120;
    /** Błędne wpisania kodu na jeden wydany kod. */
    const MAX_CODE_ATTEMPTS   = 5;
    const MAX_CODES_PER_USER  = 5;
    /** Dzienny limit kodów na konto — ogranicza zgadywanie 4-cyfrowego kodu (maks. 8×5 = 40 prób/dobę). */
    const MAX_CODES_PER_USER_DAY = 8;
    const MAX_MAILS_PER_IP    = 10;
    const HOUR                = 3600;
    const TOKEN_TTL           = 7200;

    /** ID użytkownika zweryfikowanego tokenem w bieżącym żądaniu (tylko pamięć procesu). */
    private static int $authorized_user_id = 0;

    public static function authorize_for_request( int $user_id ): void {
        self::$authorized_user_id = $user_id;
    }

    public static function is_authorized_token_user( int $user_id ): bool {
        return $user_id > 0 && self::$authorized_user_id === $user_id;
    }

    public static function is_enabled(): bool {
        return (int) get_option( self::OPTION_ENABLED, 1 ) === 1;
    }

    public static function resend_seconds(): int {
        return max( 1, (int) apply_filters( 'openvote_identity_resend_seconds', self::RESEND_SECONDS ) );
    }

    public static function normalize_email( string $s ): string {
        return mb_strtolower( trim( $s ), 'UTF-8' );
    }

    /** Najniższe ID użytkownika o danym e-mailu (pole z Field_Map) lub 0. */
    public static function find_user_by_email( string $email ): int {
        global $wpdb;
        $email  = self::normalize_email( $email );
        $actual = Openvote_Field_Map::get_field( 'email' );
        if ( $email === '' || $actual === '' || $actual === Openvote_Field_Map::NOT_SET_KEY ) {
            return 0;
        }
        if ( Openvote_Field_Map::is_core_field( $actual ) ) {
            if ( ! in_array( $actual, Openvote_Field_Map::CORE_USER_FIELDS, true ) ) {
                return 0;
            }
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ID AS uid, %i AS val FROM %i WHERE %i = %s ORDER BY ID ASC', $actual, $wpdb->users, $actual, $email ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id AS uid, meta_value AS val FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY user_id ASC', $wpdb->usermeta, $actual, $email ) );
        }
        foreach ( (array) $rows as $r ) {
            if ( self::normalize_email( (string) $r->val ) === $email && get_userdata( (int) $r->uid ) ) {
                return (int) $r->uid;
            }
        }
        return 0;
    }

    /**
     * @return object|\WP_Error  cel (głosowanie / ankieta), musi być aktywny.
     */
    public static function get_target( string $type, int $id ): object {
        if ( $type === 'poll' ) {
            $t = Openvote_Poll::get( $id );
            if ( ! $t ) {
                return new \WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'openvote' ), [ 'status' => 404 ] );
            }
            if ( ! Openvote_Poll::is_active( $t ) ) {
                return new \WP_Error( 'target_not_active', __( 'Głosowanie nie jest aktywne.', 'openvote' ), [ 'status' => 403 ] );
            }
            return $t;
        }
        if ( $type === 'survey' ) {
            $t = Openvote_Survey::get( $id );
            if ( ! $t ) {
                return new \WP_Error( 'survey_not_found', __( 'Ankieta nie istnieje.', 'openvote' ), [ 'status' => 404 ] );
            }
            if ( ! Openvote_Survey::is_active( $t ) ) {
                return new \WP_Error( 'target_not_active', __( 'Ankieta nie jest aktualnie aktywna.', 'openvote' ), [ 'status' => 403 ] );
            }
            return $t;
        }
        return new \WP_Error( 'invalid_target', __( 'Nieprawidłowy typ celu.', 'openvote' ), [ 'status' => 400 ] );
    }

    // ── Pomocnicze: klucze, liczniki okienkowe ───────────────────────────────

    public static function client_ip(): string {
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    }

    private static function h( string $s ): string {
        return substr( hash_hmac( 'sha256', $s, wp_salt( 'auth' ) ), 0, 32 );
    }

    /** Licznik z oknem czasowym liczonym od pierwszego zdarzenia: [count, start]. */
    private static function window_count( string $key, int $window ): int {
        $d = get_transient( $key );
        if ( ! is_array( $d ) || ( $d['start'] ?? 0 ) + $window <= time() ) {
            return 0;
        }
        return (int) $d['count'];
    }

    private static function window_incr( string $key, int $window ): void {
        $d = get_transient( $key );
        if ( ! is_array( $d ) || ( $d['start'] ?? 0 ) + $window <= time() ) {
            $d = [ 'count' => 0, 'start' => time() ];
        }
        $d['count']++;
        set_transient( $key, $d, max( 1, $d['start'] + $window - time() ) );
    }

    // ── Limit nieudanych wyszukań e-mail (IP, wszystkie cele, 24 h) ──────────

    private static function lookup_key(): string {
        return 'ov_idl_' . self::h( 'ip|' . self::client_ip() );
    }

    public static function failed_lookups(): int {
        return self::window_count( self::lookup_key(), self::LOOKUP_WINDOW );
    }

    public static function attempts_left(): int {
        return max( 0, self::MAX_ATTEMPTS - self::failed_lookups() );
    }

    public static function is_locked(): bool {
        return self::failed_lookups() >= self::MAX_ATTEMPTS;
    }

    public static function record_failed_lookup(): void {
        self::window_incr( self::lookup_key(), self::LOOKUP_WINDOW );
    }

    // ── Kody ─────────────────────────────────────────────────────────────────

    private static function code_key( int $uid ): string {
        return 'ov_idc_' . self::h( 'u|' . $uid );
    }

    private static function cooldown_key( int $uid ): string {
        return 'ov_idcd_' . self::h( 'u|' . $uid );
    }

    private static function code_hash( int $uid, string $code ): string {
        return hash_hmac( 'sha256', $uid . '|' . $code, wp_salt( 'auth' ) );
    }

    /** Pozostałe sekundy cooldownu ponownej wysyłki dla użytkownika (0 = można wysłać). */
    public static function cooldown_left( int $uid ): int {
        $until = (int) get_transient( self::cooldown_key( $uid ) );
        return max( 0, $until - time() );
    }

    public static function user_code_limit_reached( int $uid ): bool {
        return self::window_count( 'ov_idu_' . self::h( 'u|' . $uid ), self::HOUR ) >= self::MAX_CODES_PER_USER
            || self::window_count( 'ov_idud_' . self::h( 'u|' . $uid ), DAY_IN_SECONDS ) >= self::MAX_CODES_PER_USER_DAY;
    }

    public static function ip_mail_limit_reached(): bool {
        return self::window_count( 'ov_idm_' . self::h( 'ip|' . self::client_ip() ), self::HOUR ) >= self::MAX_MAILS_PER_IP;
    }

    /**
     * Generuje nowy kod (unieważnia poprzedni), wysyła e-mailem. Liczniki naliczane dopiero po udanej wysyłce.
     *
     * @return true|\WP_Error
     */
    public static function issue_and_send_code( int $uid ) {
        $user = get_userdata( $uid );
        if ( ! $user ) {
            return new \WP_Error( 'code_send_failed', '' );
        }
        $code = str_pad( (string) random_int( 0, 9999 ), self::CODE_LENGTH, '0', STR_PAD_LEFT );

        $site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $brand = openvote_get_brand_short_name();
        /* translators: 1: code, 2: brand */
        $subject = sprintf( __( 'Kod potwierdzający: %1$s — %2$s', 'openvote' ), $code, $brand );
        $body    = sprintf(
            /* translators: 1: site name, 2: code */
            __( "Twój kod potwierdzający do głosowania/ankiety w serwisie %1\$s: %2\$s\n\nKod jest ważny 15 minut. Jeśli to nie Ty prosiłeś o kod, zignoruj tę wiadomość.", 'openvote' ),
            $site,
            $code
        );

        // Zapis kodu przed wysyłką; przy błędzie wysyłki jest usuwany.
        set_transient( self::code_key( $uid ), [
            'hash'     => self::code_hash( $uid, $code ),
            'attempts' => 0,
            'issued'   => time(),
        ], self::CODE_TTL );

        $sent = Openvote_Mailer::send_single( (string) $user->user_email, $subject, $body );
        if ( is_wp_error( $sent ) ) {
            delete_transient( self::code_key( $uid ) );
            return $sent;
        }

        // Tylko lokalne środowisko deweloperskie + jawna stała.
        if ( defined( 'OPENVOTE_IDENTITY_DEBUG' ) && OPENVOTE_IDENTITY_DEBUG && function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
            error_log( sprintf( 'OPENVOTE_IDENTITY_DEBUG user=%d code=%s', $uid, $code ) );
        }

        $resend = self::resend_seconds();
        set_transient( self::cooldown_key( $uid ), time() + $resend, $resend );
        self::window_incr( 'ov_idu_' . self::h( 'u|' . $uid ), self::HOUR );
        self::window_incr( 'ov_idud_' . self::h( 'u|' . $uid ), DAY_IN_SECONDS );
        self::window_incr( 'ov_idm_' . self::h( 'ip|' . self::client_ip() ), self::HOUR );
        return true;
    }

    /**
     * @return string 'ok' | 'wrong' | 'expired'
     */
    public static function check_code( int $uid, string $code, int &$attempts_left ): string {
        $key = self::code_key( $uid );
        $d   = get_transient( $key );
        $attempts_left = 0;
        if ( ! is_array( $d ) || empty( $d['hash'] ) || (int) ( $d['attempts'] ?? 0 ) >= self::MAX_CODE_ATTEMPTS ) {
            delete_transient( $key );
            return 'expired';
        }
        if ( hash_equals( (string) $d['hash'], self::code_hash( $uid, $code ) ) ) {
            delete_transient( $key );
            return 'ok';
        }
        $d['attempts'] = (int) $d['attempts'] + 1;
        if ( $d['attempts'] >= self::MAX_CODE_ATTEMPTS ) {
            delete_transient( $key );
            return 'expired';
        }
        $remaining = max( 1, self::CODE_TTL - ( time() - (int) $d['issued'] ) );
        set_transient( $key, $d, $remaining );
        $attempts_left = self::MAX_CODE_ATTEMPTS - $d['attempts'];
        return 'wrong';
    }

    // ── Tokeny (zakres globalny) ─────────────────────────────────────────────

    private static function token_key( string $token ): string {
        return 'ov_idt_' . hash( 'sha256', $token );
    }

    public static function issue_token( int $user_id ): string {
        $token = bin2hex( random_bytes( 20 ) );
        set_transient( self::token_key( $token ), [ 'user_id' => $user_id, 'created' => time() ], self::TOKEN_TTL );
        return $token;
    }

    public static function get_request_token( \WP_REST_Request $r ): string {
        $t = (string) $r->get_header( 'x_openvote_identity' );
        if ( $t === '' ) {
            return '';
        }
        return preg_match( '/^[A-Za-z0-9]{20,128}$/', $t ) ? $t : 'invalid';
    }

    /** user_id tokenu (ważny dla dowolnego celu) lub 0. $type/$id zachowane dla zgodności wywołań. */
    public static function token_user_id( string $token, string $type = '', int $id = 0 ): int {
        if ( $token === '' || $token === 'invalid' || ! self::is_enabled() ) {
            return 0;
        }
        $d = get_transient( self::token_key( $token ) );
        if ( ! is_array( $d ) ) {
            return 0;
        }
        $uid = (int) ( $d['user_id'] ?? 0 );
        return ( $uid > 0 && get_userdata( $uid ) ) ? $uid : 0;
    }

    /** Zalogowany użytkownik, inaczej użytkownik z ważnego tokenu, inaczej 0. */
    public static function resolve_user_id( \WP_REST_Request $r, string $target_type = '', int $target_id = 0 ): int {
        if ( is_user_logged_in() ) {
            return get_current_user_id();
        }
        $uid = self::token_user_id( self::get_request_token( $r ) );
        if ( $uid > 0 ) {
            self::authorize_for_request( $uid );
        }
        return $uid;
    }

    /** Callback uprawnień: zalogowany LUB ważny token. */
    public static function permission_for( \WP_REST_Request $r, string $target_type = '' ): true|\WP_Error {
        if ( is_user_logged_in() ) {
            return true;
        }
        $token = self::get_request_token( $r );
        if ( $token === '' ) {
            return new \WP_Error( 'rest_forbidden', __( 'Przepraszamy, nie możesz tego zrobić.', 'openvote' ), [ 'status' => 401 ] );
        }
        if ( self::token_user_id( $token ) > 0 ) {
            return true;
        }
        return self::invalid_error();
    }

    public static function invalid_error(): \WP_Error {
        return new \WP_Error( 'identity_invalid', __( 'Weryfikacja wygasła — potwierdź adres e-mail ponownie.', 'openvote' ), [ 'status' => 401 ] );
    }

    public static function locked_message(): string {
        return __( 'Wykorzystano 5 z 5 prób. Twój głos nie zostanie policzony do wyników. W każdej chwili możesz się zalogować lub skorzystać z opcji »Nie pamiętam hasła«, odzyskać dostęp do konta i oddać głos, dopóki głosowanie jest aktywne.', 'openvote' );
    }

    public static function send_nocache(): void {
        nocache_headers();
        do_action( 'litespeed_control_set_nocache', 'openvote identity' );
    }
}
