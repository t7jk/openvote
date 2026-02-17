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
                'id' => [
                    'validate_callback' => fn( $param ) => is_numeric( $param ),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // Cast vote.
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/vote', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'cast_vote' ],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $param ) => is_numeric( $param ),
                    'sanitize_callback' => 'absint',
                ],
                'answers' => [
                    'required'          => true,
                    'validate_callback' => fn( $param ) => is_array( $param ),
                ],
            ],
        ] );

        // Get results.
        register_rest_route( self::NAMESPACE, '/polls/(?P<id>\d+)/results', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_results' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $param ) => is_numeric( $param ),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    public function get_polls( WP_REST_Request $request ): WP_REST_Response {
        $polls = Evoting_Poll::get_all( [ 'limit' => 100 ] );

        $data = array_map( function ( $poll ) {
            return [
                'id'         => (int) $poll->id,
                'title'      => $poll->title,
                'status'     => $poll->status,
                'start_date' => $poll->start_date,
                'end_date'   => $poll->end_date,
            ];
        }, $polls );

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

        $data = [
            'id'          => (int) $poll->id,
            'title'       => $poll->title,
            'description' => $poll->description,
            'status'      => $poll->status,
            'start_date'  => $poll->start_date,
            'end_date'    => $poll->end_date,
            'is_active'   => $is_active,
            'is_ended'    => $is_ended,
            'has_voted'   => $has_voted,
            'questions'   => array_map( fn( $q ) => [
                'id'   => (int) $q->id,
                'text' => $q->question_text,
            ], $poll->questions ),
        ];

        return new WP_REST_Response( $data, 200 );
    }

    public function cast_vote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $poll_id = $request->get_param( 'id' );
        $answers = $request->get_param( 'answers' );
        $user_id = get_current_user_id();

        // Sanitize answers: keys to int, values to string.
        $clean_answers = [];
        foreach ( $answers as $question_id => $answer ) {
            $clean_answers[ absint( $question_id ) ] = sanitize_text_field( $answer );
        }

        $result = Evoting_Vote::cast( $poll_id, $user_id, $clean_answers );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Twój głos został zapisany.', 'evoting' ),
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

        $results = Evoting_Vote::get_results( $poll_id );
        $voters  = Evoting_Vote::get_voters_anonymous( $poll_id );

        // Public endpoint – only pseudonyms, no identifying data.
        $anonymous_voters = array_map( fn( $v ) => [
            'pseudonym' => $v['pseudonym'],
        ], $voters );

        return new WP_REST_Response( [
            'poll_id'      => $poll_id,
            'title'        => $poll->title,
            'total_voters' => $results['total_voters'],
            'questions'    => $results['questions'],
            'voters'       => $anonymous_voters,
        ], 200 );
    }
}
