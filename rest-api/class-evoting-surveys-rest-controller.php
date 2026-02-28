<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Surveys_Rest_Controller {

    private const NAMESPACE = 'evoting/v1';

    public function register_routes(): void {
        // GET /surveys — lista aktywnych ankiet (dla bloku/strony publicznej).
        register_rest_route( self::NAMESPACE, '/surveys', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_surveys' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /surveys/{id} — dane pojedynczej ankiety.
        register_rest_route( self::NAMESPACE, '/surveys/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_survey' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // POST /surveys/{id}/submit — zapis lub aktualizacja odpowiedzi.
        register_rest_route( self::NAMESPACE, '/surveys/(?P<id>\d+)/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'submit_response' ],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'id'     => [ 'sanitize_callback' => 'absint' ],
                'status' => [
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => [ 'draft', 'ready' ],
                ],
                'answers' => [
                    'required'          => true,
                    'validate_callback' => fn( $p ) => is_array( $p ),
                ],
            ],
        ] );

        // GET /surveys/{id}/my-response — pobierz swoją odpowiedź.
        register_rest_route( self::NAMESPACE, '/surveys/(?P<id>\d+)/my-response', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_my_response' ],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // POST /profile/complete — zapisz brakujące pola profilu użytkownika.
        register_rest_route( self::NAMESPACE, '/profile/complete', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'complete_profile' ],
            'permission_callback' => fn() => is_user_logged_in(),
        ] );
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    public function get_surveys( WP_REST_Request $request ): WP_REST_Response {
        $surveys = Evoting_Survey::get_all( [ 'status' => 'open' ] );
        $now     = current_time( 'mysql' );

        $result = [];
        foreach ( $surveys as $s ) {
            if ( $s->date_start > $now || $s->date_end < $now ) {
                continue;
            }
            $result[] = $this->format_survey( $s, false );
        }

        return rest_ensure_response( $result );
    }

    public function get_survey( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $survey_id = $request->get_param( 'id' );
        $survey    = Evoting_Survey::get( $survey_id );

        if ( ! $survey ) {
            return new WP_Error( 'survey_not_found', __( 'Ankieta nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        $user_id  = get_current_user_id();
        $response = $user_id ? Evoting_Survey::get_user_response( $survey_id, $user_id ) : null;

        $data = $this->format_survey( $survey, true );
        $data['my_response'] = $response ? [
            'status'  => $response->response_status,
            'answers' => $response->answers,
        ] : null;

        return rest_ensure_response( $data );
    }

    public function submit_response( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $survey_id = $request->get_param( 'id' );
        $user_id   = get_current_user_id();

        $survey = Evoting_Survey::get( $survey_id );
        if ( ! $survey ) {
            return new WP_Error( 'survey_not_found', __( 'Ankieta nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        // Ankieta musi być otwarta.
        if ( ! Evoting_Survey::is_active( $survey ) ) {
            return new WP_Error( 'survey_closed', __( 'Ankieta nie jest aktualnie aktywna.', 'evoting' ), [ 'status' => 403 ] );
        }

        // Sprawdź kompletność profilu.
        $profile_check = Evoting_Survey::check_user_profile( $user_id );
        if ( ! $profile_check['ok'] ) {
            return new WP_Error(
                'incomplete_profile',
                sprintf(
                    __( 'Uzupełnij profil przed wypełnieniem ankiety. Brakujące pola: %s', 'evoting' ),
                    implode( ', ', $profile_check['missing'] )
                ),
                [ 'status' => 403 ]
            );
        }

        // Walidacja odpowiedzi.
        $questions = Evoting_Survey::get_questions( $survey_id );
        $raw_answers = (array) $request->get_param( 'answers' ); // [question_id => text]
        $clean_answers = [];

        foreach ( $questions as $q ) {
            $qid  = (int) $q->id;
            $text = isset( $raw_answers[ $qid ] ) ? sanitize_textarea_field( (string) $raw_answers[ $qid ] ) : '';

            // Ogranicz do max_chars.
            if ( mb_strlen( $text ) > (int) $q->max_chars ) {
                $text = mb_substr( $text, 0, (int) $q->max_chars );
            }
            $clean_answers[ $qid ] = $text;
        }

        $response_status = sanitize_key( $request->get_param( 'status' ) );
        if ( ! in_array( $response_status, [ 'draft', 'ready' ], true ) ) {
            $response_status = 'draft';
        }

        $user_data = Evoting_Survey::get_user_profile_data( $user_id );

        $saved = Evoting_Survey::save_response( $survey_id, $user_id, $clean_answers, $response_status, $user_data );

        if ( ! $saved ) {
            return new WP_Error( 'save_error', __( 'Błąd zapisu odpowiedzi.', 'evoting' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'status'  => $response_status,
            'message' => 'ready' === $response_status
                ? __( 'Twoja odpowiedź została zapisana jako Gotowa.', 'evoting' )
                : __( 'Twoja odpowiedź została zapisana jako Szkic.', 'evoting' ),
        ] );
    }

    public function get_my_response( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $survey_id = $request->get_param( 'id' );
        $user_id   = get_current_user_id();

        $survey = Evoting_Survey::get( $survey_id );
        if ( ! $survey ) {
            return new WP_Error( 'survey_not_found', __( 'Ankieta nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        $response = Evoting_Survey::get_user_response( $survey_id, $user_id );

        if ( ! $response ) {
            return rest_ensure_response( null );
        }

        return rest_ensure_response( [
            'status'  => $response->response_status,
            'answers' => $response->answers,
        ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function format_survey( object $survey, bool $include_questions ): array {
        $data = [
            'id'          => (int) $survey->id,
            'title'       => $survey->title,
            'description' => $survey->description,
            'status'      => $survey->status,
            'date_start'  => $survey->date_start,
            'date_end'    => $survey->date_end,
        ];

        if ( $include_questions ) {
            $questions = Evoting_Survey::get_questions( (int) $survey->id );
            $data['questions'] = array_map( fn( $q ) => [
                'id'         => (int) $q->id,
                'body'       => $q->body,
                'field_type' => $q->field_type,
                'max_chars'  => (int) $q->max_chars,
            ], $questions );
        }

        return $data;
    }

    /**
     * POST /profile/complete
     * Zapisuje brakujące pola profilu użytkownika.
     * Body JSON: { "fields": { "first_name": "Jan", "phone": "123456789", ... } }
     */
    public function complete_profile( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        $body    = $request->get_json_params();
        $fields  = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : [];

        if ( empty( $fields ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Brak danych do zapisania.', 'evoting' ) ], 400 );
        }

        $allowed = array_keys( Evoting_Field_Map::DEFAULTS );

        foreach ( $fields as $logical => $raw_value ) {
            $logical = sanitize_key( $logical );
            if ( ! in_array( $logical, $allowed, true ) ) {
                continue;
            }

            $value      = sanitize_text_field( (string) $raw_value );
            $actual_key = Evoting_Field_Map::get_field( $logical );

            if ( '' === $value ) {
                continue;
            }

            if ( 'email' === $logical ) {
                if ( ! is_email( $value ) ) {
                    return new WP_REST_Response(
                        [ 'success' => false, 'message' => __( 'Nieprawidłowy adres e-mail.', 'evoting' ) ],
                        400
                    );
                }
                wp_update_user( [ 'ID' => $user_id, 'user_email' => $value ] );

            } elseif ( in_array( $logical, [ 'first_name', 'last_name', 'nickname' ], true ) ) {
                // Pola dostępne bezpośrednio w usermeta (WordPress odczytuje je z meta).
                update_user_meta( $user_id, $logical, $value );
                if ( 'nickname' === $logical ) {
                    // Zaktualizuj też display_name jeśli był taki sam jak stary nickname.
                    $user = get_userdata( $user_id );
                    if ( $user && $user->display_name === $user->nickname ) {
                        wp_update_user( [ 'ID' => $user_id, 'display_name' => $value ] );
                    }
                    wp_update_user( [ 'ID' => $user_id, 'nickname' => $value ] );
                }

            } elseif ( Evoting_Field_Map::NOT_SET_KEY === $actual_key ) {
                // Pole nie jest zmapowane — dla 'phone' zapisz do evoting_phone i zaktualizuj mapowanie.
                if ( 'phone' === $logical ) {
                    update_user_meta( $user_id, 'evoting_phone', $value );
                    $map = Evoting_Field_Map::get();
                    if ( Evoting_Field_Map::NOT_SET_KEY === $map['phone'] ) {
                        $map['phone'] = 'evoting_phone';
                        update_option( Evoting_Field_Map::OPTION_KEY, $map, false );
                    }
                }
                // Pozostałe niezmapowane pola — pomijamy.

            } elseif ( Evoting_Field_Map::is_core_field( $actual_key ) ) {
                wp_update_user( [ 'ID' => $user_id, $actual_key => $value ] );

            } else {
                update_user_meta( $user_id, $actual_key, $value );
            }
        }

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }
}
