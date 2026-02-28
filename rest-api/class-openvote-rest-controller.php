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
            'permission_callback' => fn() => current_user_can( 'manage_options' ) || current_user_can( 'openvote_admin' ),
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    public function get_polls( WP_REST_Request $request ): WP_REST_Response {
        $polls = Openvote_Poll::get_all( [ 'limit' => 100 ] );

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

        // Tłumimy wyjście DB podczas tworzenia zadania — błędy SQL nie mogą trafić do odpowiedzi JSON.
        global $wpdb;
        $suppress = $wpdb->suppress_errors( true );

        try {
            $job_id = Openvote_Batch_Processor::start_job( 'send_invitations', [ 'poll_id' => $poll_id ] );
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
}
