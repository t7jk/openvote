<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Rest_Controller {

    private const NAMESPACE = 'evoting/v1';

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

        // Get results (public after poll ends).
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/results', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_results' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    public function get_polls( WP_REST_Request $request ): WP_REST_Response {
        $polls = Evoting_Poll::get_all( [ 'limit' => 100 ] );

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
        $poll = Evoting_Poll::get( $request->get_param( 'id' ) );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'evoting' ), [ 'status' => 404 ] );
        }

        $is_active = Evoting_Poll::is_active( $poll );
        $is_ended  = Evoting_Poll::is_ended( $poll );
        $has_voted = is_user_logged_in() ? Evoting_Vote::has_voted( (int) $poll->id, get_current_user_id() ) : false;

        // Check eligibility for logged-in users (pełne 7 sprawdzeń).
        $eligible_error = null;
        if ( is_user_logged_in() && $is_active && ! $has_voted ) {
            $check = Evoting_Eligibility::can_vote( get_current_user_id(), (int) $poll->id );
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
        $is_anonymous = (bool) $request->get_param( 'is_anonymous' );
        $user_id      = get_current_user_id();

        // Sanitize: question_id → int, answer_id → int.
        $clean_answers = [];
        foreach ( $answers as $question_id => $answer_id ) {
            $clean_answers[ absint( $question_id ) ] = absint( $answer_id );
        }

        $result = Evoting_Vote::cast( $poll_id, $user_id, $clean_answers, $is_anonymous );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Twój głos został zapisany. Dziękujemy!', 'evoting' ),
        ], 200 );
    }

    public function get_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id = $request->get_param( 'id' );
        $poll    = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new WP_Error( 'not_found', __( 'Głosowanie nie zostało znalezione.', 'evoting' ), [ 'status' => 404 ] );
        }

        if ( ! Evoting_Poll::is_ended( $poll ) ) {
            return new WP_Error( 'poll_not_ended', __( 'Wyniki dostępne po zakończeniu głosowania.', 'evoting' ), [ 'status' => 403 ] );
        }

        $results    = Evoting_Vote::get_results( $poll_id );
        $voters     = Evoting_Vote::get_voters_anonymous( $poll_id );
        $non_voters = Evoting_Vote::get_non_voters( $poll_id );

        return new WP_REST_Response( [
            'poll_id'          => $poll_id,
            'title'            => $poll->title,
            'total_eligible'   => $results['total_eligible'],
            'total_voters'     => $results['total_voters'],
            'non_voters'       => $results['non_voters'],
            'questions'        => $results['questions'],
            'voters'           => $voters,
            'non_voters_list'  => $non_voters,
        ], 200 );
    }
}
