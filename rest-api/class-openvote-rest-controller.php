<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Rest_Controller {

    private const NAMESPACE = 'openvote/v1';

    public function register_routes(): void {
        // List polls (for editor dropdown).
        register_rest_route( self::NAMESPACE, '/polls', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_polls' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        // Get single poll.
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_poll' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Cast vote.
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/vote', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'cast_vote' ],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'id'           => [ 'sanitize_callback' => 'absint' ],
                'answers'      => [
                    'required'          => true,
                    'validate_callback' => fn( $p ) => is_array( $p ),
                ],
                'is_anonymous' => [
                    'required' => true,
                    'type'     => 'boolean',
                ],
            ],
        ] );

        // Get results (public after poll ends). Listy głosujących i niegłosujących są zwracane partiami (domyślnie po 100) ze względu na wydajność.
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/results', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_results' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id'                  => [ 'sanitize_callback' => 'absint' ],
                'voters_limit'        => [ 'sanitize_callback' => 'absint', 'default' => 100 ],
                'voters_offset'       => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
                'non_voters_limit'    => [ 'sanitize_callback' => 'absint', 'default' => 100 ],
                'non_voters_offset'   => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
            ],
        ] );

        // Send invitations (start batch job).
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/send-invitations', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'send_invitations' ],
            'permission_callback' => fn() => current_user_can( 'edit_others_posts' ) || current_user_can( 'publish_posts' ) || Openvote_Admin::user_can_access_coordinators(),
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Zaplanuj automatyczne wznowienie wysyłki zaproszeń o północy (gdy limit dzienny).
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/schedule-email-resume', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'schedule_email_resume' ],
            'permission_callback' => fn() => current_user_can( 'edit_others_posts' ) || current_user_can( 'publish_posts' ) || Openvote_Admin::user_can_access_coordinators(),
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Sprawdź konfigurację (metoda wysyłki, mapowanie pól).
        register_rest_route( self::NAMESPACE, '/check-config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'check_config' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );

        // Naprawa błędu braku miast: ustaw „Nie używaj miast”, grupa Wszyscy, wyłącz wymaganie.
        register_rest_route( self::NAMESPACE, '/repair-city-no-groups', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'repair_city_no_groups' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );
    }

    public function get_polls( WP_REST_Request $request ): WP_REST_Response {
        $polls = Openvote_Poll::get_all( [ 'limit' => 100 ] );

        if ( openvote_is_coordinator_restricted_to_own_groups() ) {
            $my_ids = Openvote_Role_Manager::get_user_groups( get_current_user_id() );
            if ( openvote_create_test_group_enabled() ) {
                $test_gid = openvote_get_test_group_id();
                if ( $test_gid ) {
                    $my_ids[] = $test_gid;
                }
            }
            $polls = array_filter( $polls, function ( $p ) use ( $my_ids ) {
                $target = Openvote_Poll::get_target_group_ids( $p );
                return ! empty( array_intersect( $target, $my_ids ) );
            } );
        }

        $data = array_map( fn( $p ) => [
            'id'         => (int) $p->id,
            'title'      => $p->title,
            'status'     => $p->status,
            'date_start' => $p->date_start,
            'date_end'   => $p->date_end,
        ], $polls );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_poll( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll = Openvote_Poll::get( $request->get_param( 'id' ) );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'openvote' ), [ 'status' => 404 ] );
        }

        $is_active = Openvote_Poll::is_active( $poll );
        $is_ended  = Openvote_Poll::is_ended( $poll );
        $has_voted = is_user_logged_in() ? Openvote_Vote::has_voted( (int) $poll->id, get_current_user_id() ) : false;

        // Check eligibility for logged-in users (pełne 7 sprawdzeń).
        $eligible_error = null;
        if ( is_user_logged_in() && $is_active && ! $has_voted ) {
            $check = Openvote_Eligibility::can_vote( get_current_user_id(), (int) $poll->id );
            if ( ! $check['eligible'] ) {
                $eligible_error = $check['reason'];
            }
        }

        return new WP_REST_Response( [
            'id'             => (int) $poll->id,
            'title'          => $poll->title,
            'description'    => $poll->description,
            'status'         => $poll->status,
            'date_start'     => $poll->date_start,
            'date_end'       => $poll->date_end,
            'is_active'      => $is_active,
            'is_ended'       => $is_ended,
            'has_voted'      => $has_voted,
            'eligible_error' => $eligible_error,
            'questions'      => array_map( fn( $q ) => [
                'id'      => (int) $q->id,
                'text'    => $q->body,
                'answers' => array_map( fn( $a ) => [
                    'id'         => (int) $a->id,
                    'text'       => $a->body,
                    'is_abstain' => (bool) $a->is_abstain,
                ], $q->answers ),
            ], $poll->questions ),
        ], 200 );
    }

    public function cast_vote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id      = $request->get_param( 'id' );
        $answers      = $request->get_param( 'answers' );
        $user_id      = get_current_user_id();

        $poll = Openvote_Poll::get( $poll_id );
        if ( $poll && isset( $poll->vote_mode ) && $poll->vote_mode === 'anonymous' ) {
            $is_anonymous = true;
        } else {
            $is_anonymous = (bool) $request->get_param( 'is_anonymous' );
        }

        // Sanitize: question_id → int, answer_id → int.
        $clean_answers = [];
        foreach ( $answers as $question_id => $answer_id ) {
            $clean_answers[ absint( $question_id ) ] = absint( $answer_id );
        }

        $result = Openvote_Vote::cast( $poll_id, $user_id, $clean_answers, $is_anonymous );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Twój głos został zapisany. Dziękujemy!', 'openvote' ),
        ], 200 );
    }

    public function get_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id = $request->get_param( 'id' );
        $poll    = Openvote_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'openvote' ), [ 'status' => 404 ] );
        }

        if ( ! Openvote_Poll::is_ended( $poll ) ) {
            return new WP_Error( 'poll_not_ended', __( 'Wyniki dostępne po zakończeniu głosowania.', 'openvote' ), [ 'status' => 403 ] );
        }

        $results = Openvote_Vote::get_results( $poll_id );

        $voters_limit      = max( 0, min( 500, (int) $request->get_param( 'voters_limit' ) ) );
        $voters_offset     = max( 0, (int) $request->get_param( 'voters_offset' ) );
        $non_voters_limit  = max( 0, min( 500, (int) $request->get_param( 'non_voters_limit' ) ) );
        $non_voters_offset = max( 0, (int) $request->get_param( 'non_voters_offset' ) );
        if ( $voters_limit === 0 ) {
            $voters_limit = 100;
        }
        if ( $non_voters_limit === 0 ) {
            $non_voters_limit = 100;
        }

        if ( isset( $poll->vote_mode ) && $poll->vote_mode === 'anonymous' ) {
            $voters     = [];
            $non_voters = [];
        } else {
            $voters     = Openvote_Vote::get_voters_anonymous( $poll_id, $voters_limit, $voters_offset );
            $non_voters = Openvote_Vote::get_non_voters( $poll_id, $non_voters_limit, $non_voters_offset );
        }

        return new WP_REST_Response( [
            'poll_id'          => $poll_id,
            'title'            => $poll->title,
            'total_eligible'   => $results['total_eligible'],
            'total_voters'     => $results['total_voters'],
            'non_voters'       => $results['non_voters'],
            'questions'        => $results['questions'],
            'voters'           => $voters,
            'non_voters_list'  => $non_voters,
            'voters_limit'     => $voters_limit,
            'voters_offset'    => $voters_offset,
            'non_voters_limit' => $non_voters_limit,
            'non_voters_offset'=> $non_voters_offset,
        ], 200 );
    }

    /**
     * POST /polls/{id}/send-invitations
     * Uruchamia zadanie wsadowej wysyłki zaproszeń i zwraca job_id.
     */
    public function send_invitations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id = absint( $request->get_param( 'id' ) );
        $poll    = Openvote_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'openvote' ), [ 'status' => 404 ] );
        }

        if ( ! in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
            return new WP_Error(
                'poll_not_active',
                __( 'Zaproszenia można wysyłać tylko do otwartych lub zakończonych głosowań.', 'openvote' ),
                [ 'status' => 400 ]
            );
        }

        $batch_size = function_exists( 'openvote_get_email_batch_size' ) ? openvote_get_email_batch_size() : 100;
        if ( class_exists( 'Openvote_Email_Rate_Limits', false ) ) {
            $limit_check = Openvote_Email_Rate_Limits::would_exceed_limits( $batch_size );
            if ( ! empty( $limit_check['exceeded'] ) ) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    $limit_check['message'],
                    [
                        'status'       => 429,
                        'limit_type'   => $limit_check['limit_type'],
                        'wait_seconds' => $limit_check['wait_seconds'],
                    ]
                );
            }
        }

        // Tłumimy wyjście DB podczas tworzenia zadania — błędy SQL nie mogą trafić do odpowiedzi JSON.
        global $wpdb;
        $suppress = $wpdb->suppress_errors( true );

        try {
            $job_id = Openvote_Batch_Processor::start_job( 'send_invitations', [ 'poll_id' => $poll_id ] );
            $actor_id = get_current_user_id();
            if ( $actor_id && isset( $poll->title ) && $poll->title !== '' ) {
                openvote_polls_audit_log_append( $actor_id, sprintf( __( 'zaprosił na głosowanie %s', 'openvote' ), $poll->title ) );
            }
        } catch ( \Throwable $e ) {
            $wpdb->suppress_errors( $suppress );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Openvote send_invitations: ' . $e->getMessage() );
            }
            return new WP_Error(
                'job_error',
                __( 'Wystąpił błąd podczas uruchamiania wysyłki zaproszeń.', 'openvote' ),
                [ 'status' => 500 ]
            );
        }

        $wpdb->suppress_errors( $suppress );

        return new WP_REST_Response( [ 'job_id' => $job_id ], 200 );
    }

    /**
     * POST /polls/{id}/schedule-email-resume
     * Dopisuje głosowanie do listy wznowienia o północy i planuje wp-cron.
     */
    public function schedule_email_resume( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id = absint( $request->get_param( 'id' ) );
        $poll    = Openvote_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'openvote' ), [ 'status' => 404 ] );
        }

        if ( ! in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
            return new WP_Error(
                'poll_not_active',
                __( 'Zaproszenia można wysyłać tylko do otwartych lub zakończonych głosowań.', 'openvote' ),
                [ 'status' => 400 ]
            );
        }

        if ( ! class_exists( 'Openvote_Email_Resume_Cron', false ) ) {
            return new WP_Error( 'unavailable', __( 'Funkcja wznowienia jest niedostępna.', 'openvote' ), [ 'status' => 503 ] );
        }

        Openvote_Email_Resume_Cron::add_poll_and_schedule( $poll_id );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * GET /check-config
     * Zwraca wynik walidacji konfiguracji: pola obowiązkowe, pola dodatkowe (Grupa, Telefon), notę o synchronizacji.
     */
    public function check_config( WP_REST_Request $request ): WP_REST_Response {
        $result = [
            'mandatory_fields' => $this->check_config_mandatory_fields(),
            'city_field'       => $this->check_config_city_field(),
            'phone_field'      => $this->check_config_phone_field(),
            'sync_note'        => '',
        ];

        if ( Openvote_Field_Map::is_city_disabled() ) {
            Openvote_Admin_Settings::ensure_wszyscy_group_exists();
        }

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * POST /repair-city-no-groups
     * Naprawa błędu braku miast: ustawia „Nie używaj miast”, tworzy grupę Wszyscy, wyłącza wymaganie Grupy.
     */
    public function repair_city_no_groups( WP_REST_Request $request ): WP_REST_Response {
        $map = (array) get_option( Openvote_Field_Map::OPTION_KEY, [] );
        $map['city'] = Openvote_Field_Map::NO_CITY_KEY;
        update_option( Openvote_Field_Map::OPTION_KEY, $map, false );

        $req = (array) get_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, [] );
        $req = array_values( array_diff( $req, [ 'city' ] ) );
        update_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, $req, false );

        $survey_req = (array) get_option( Openvote_Field_Map::SURVEY_REQUIRED_FIELDS_OPTION, [] );
        $survey_req = array_values( array_diff( $survey_req, [ 'city' ] ) );
        update_option( Openvote_Field_Map::SURVEY_REQUIRED_FIELDS_OPTION, $survey_req, false );

        Openvote_Admin_Settings::ensure_wszyscy_group_exists();

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Naprawiono: włączono tryb „Nie używaj miast”, utworzono grupę „Wszyscy”, wyłączono wymaganie pola Grupa. Odśwież sprawdzenie konfiguracji.', 'openvote' ),
        ], 200 );
    }

    /**
     * Sprawdza, czy pola obowiązkowe (Imię, Nazwisko, e-mail) są zmapowane.
     *
     * @return array{ok: bool, message: string}
     */
    private function check_config_mandatory_fields(): array {
        $map   = Openvote_Field_Map::get();
        $labels = Openvote_Field_Map::LABELS;
        $not_set = Openvote_Field_Map::NOT_SET_KEY;
        $required_keys = [ 'first_name', 'last_name', 'email' ];
        $missing = [];
        foreach ( $required_keys as $logical ) {
            $actual = $map[ $logical ] ?? $not_set;
            if ( $actual === $not_set || $actual === '' ) {
                $missing[] = $labels[ $logical ] ?? $logical;
            }
        }
        if ( ! empty( $missing ) ) {
            return [
                'ok'      => false,
                'message' => __( 'Pola obowiązkowe do głosowania i ankiet nie są zmapowane:', 'openvote' ) . ' ' . implode( ', ', $missing ) . '. ' . __( 'Bez tego użytkownicy nie przejdą weryfikacji uprawnień do głosowania ani nie będą mogli składać zgłoszeń do ankiet. Ustaw w sekcji Mapowanie pól (poniżej) klucze wbudowane w WordPress (np. first_name, last_name, user_email) lub odpowiadające im klucze z wp_usermeta.', 'openvote' ),
            ];
        }
        return [
            'ok'      => true,
            'message' => __( 'Pola Imię, Nazwisko i E-mail są zmapowane na dane z profilu użytkownika WordPress. System używa ich przy weryfikacji uprawnień do głosowania (czy profil jest kompletny) oraz przy wypełnianiu ankiet i zgłoszeń. Mapowanie zmieniasz w tabeli „Mapowanie pól” na tej stronie.', 'openvote' ),
        ];
    }

    /**
     * Sprawdza pole Grupa (city).
     *
     * @return array{ok: bool, message: string}
     */
    private function check_config_city_field(): array {
        $not_set  = Openvote_Field_Map::NOT_SET_KEY;
        $no_city  = Openvote_Field_Map::NO_CITY_KEY;
        $city_val = Openvote_Field_Map::get_field( 'city' );

        if ( $city_val === $not_set || $city_val === '' ) {
            return [
                'ok'      => false,
                'message' => __( 'Pole nie jest zmapowane. Jest ono konieczne do przypisywania użytkowników do grup i do targetowania głosowań. Możesz przełączyć na opcję „Nie używaj miast (wszyscy w grupie Wszyscy)” w tabeli Mapowanie pól poniżej — wtedy wszyscy użytkownicy trafią do jednej grupy „Wszyscy”, a grupa zostanie utworzona automatycznie.', 'openvote' ),
            ];
        }
        if ( $city_val === $no_city ) {
            $msg = __(
                'Nie jest zmapowane pole Grupa. Bez posiadania takiego pola w bazie danych z nazwą grupy dla danego użytkownika, wszystkie głosowania są organizowane w jednej grupie Wszyscy. Synchronizacja użytkowników będzie przypisywać użytkowników do jednej grupy. To ogranicza możliwości. ZALECENIE. Dodaj pole linkiem niżej, a następnie wymuś wymaganie tego pola dla głosowania oraz ankiety. Utwórz ręcznie nazwy grup w systemie. Zorganizuj głosowanie dla wszystkich użytkowników, to spowoduje, że każdy będzie musiał wypełnić pole grupy. W ten sposób użytkownicy samodzielnie wypełnią pole, dokonaj synchronizacji co doda wszystkich do właściwych grup.',
                'openvote'
            );
            return [
                'ok'              => true,
                'not_recommended' => true,
                'message'         => $msg,
            ];
        }
        return [
            'ok'      => true,
            'message' => __( 'Pole jest zmapowane. Wartości z profilu użytkownika służą do przypisywania do grup i targetowania głosowań. Mapowanie zmieniasz w tabeli Mapowanie pól.', 'openvote' ),
        ];
    }

    /**
     * Sprawdza pole Telefon.
     *
     * @return array{ok: bool, message: string}
     */
    private function check_config_phone_field(): array {
        $not_set             = Openvote_Field_Map::NOT_SET_KEY;
        $phone_val           = Openvote_Field_Map::get_field( 'phone' );
        $phone_required_survey = Openvote_Field_Map::is_survey_required( 'phone' );

        if ( $phone_required_survey && ( $phone_val === $not_set || $phone_val === '' ) ) {
            return [
                'ok'      => false,
                'message' => __(
                    'Telefon: Nie jest zmapowane pole Telefon. Bez posiadania takiego pola w bazie danych z nr telefonu dla danego użytkownika masz utrudnienia w organizacji Ankiet. ZALECENIE. Dodaj pole linkiem niżej, a następnie wymuś wymaganie tego pola dla głosowania oraz ankiety. Zorganizuj głosowanie dla wszystkich użytkowników, to spowoduje, że każdy będzie musiał wypełnić pole telefonu. W ten sposób użytkownicy samodzielnie wypełnią pole. Potem odznacz wymaganie telefonu dla Głosowań. A zostaw dla Ankiet.',
                    'openvote'
                ),
            ];
        }
        if ( $phone_val === $not_set || $phone_val === '' ) {
            return [
                'ok'      => true,
                'message' => __( 'Pole nie jest zmapowane. Ankiety działają poprawnie — telefon nie jest obowiązkowy, dopóki nie zaznaczysz „Wymagane do ankiety” przy Telefonie w Mapowaniu pól. Jeśli kiedyś włączysz wymaganie, trzeba będzie zmapować klucz (np. user_gsm).', 'openvote' ),
            ];
        }
        return [
            'ok'      => true,
            'message' => __( 'Pole jest zmapowane. Używane w ankietach zgodnie z ustawieniem „Wymagane do ankiety” w tabeli Mapowanie pól.', 'openvote' ),
        ];
    }
}
