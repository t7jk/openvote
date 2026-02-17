<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Poll {

    private static function polls_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_polls';
    }

    private static function questions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_questions';
    }

    /**
     * Create a new poll with questions.
     *
     * @param array{title: string, description?: string, status?: string, start_date: string, end_date: string, notify_users?: bool, questions: string[]} $data
     * @return int|false Poll ID or false on failure.
     */
    public static function create( array $data ): int|false {
        global $wpdb;

        $result = $wpdb->insert(
            self::polls_table(),
            [
                'title'        => sanitize_text_field( $data['title'] ),
                'description'  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
                'status'       => $data['status'] ?? 'draft',
                'start_date'   => $data['start_date'],
                'end_date'     => $data['end_date'],
                'notify_users' => ! empty( $data['notify_users'] ) ? 1 : 0,
                'created_by'   => get_current_user_id(),
                'created_at'   => current_time( 'mysql' ),
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        $poll_id = (int) $wpdb->insert_id;

        if ( ! empty( $data['questions'] ) ) {
            self::save_questions( $poll_id, $data['questions'] );
        }

        return $poll_id;
    }

    /**
     * Update an existing poll.
     *
     * @param int   $poll_id
     * @param array $data
     * @return bool
     */
    public static function update( int $poll_id, array $data ): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if ( isset( $data['title'] ) ) {
            $update['title'] = sanitize_text_field( $data['title'] );
            $format[]        = '%s';
        }
        if ( array_key_exists( 'description', $data ) ) {
            $update['description'] = $data['description'] ? sanitize_textarea_field( $data['description'] ) : null;
            $format[]              = '%s';
        }
        if ( isset( $data['status'] ) ) {
            $update['status'] = sanitize_text_field( $data['status'] );
            $format[]         = '%s';
        }
        if ( isset( $data['start_date'] ) ) {
            $update['start_date'] = $data['start_date'];
            $format[]             = '%s';
        }
        if ( isset( $data['end_date'] ) ) {
            $update['end_date'] = $data['end_date'];
            $format[]           = '%s';
        }
        if ( isset( $data['notify_users'] ) ) {
            $update['notify_users'] = ! empty( $data['notify_users'] ) ? 1 : 0;
            $format[]               = '%d';
        }

        $update['updated_at'] = current_time( 'mysql' );
        $format[]             = '%s';

        $result = $wpdb->update( self::polls_table(), $update, [ 'id' => $poll_id ], $format, [ '%d' ] );

        if ( isset( $data['questions'] ) ) {
            self::save_questions( $poll_id, $data['questions'] );
        }

        return false !== $result;
    }

    /**
     * Save questions for a poll (replace all).
     *
     * @param int      $poll_id
     * @param string[] $questions Array of question texts.
     */
    public static function save_questions( int $poll_id, array $questions ): void {
        global $wpdb;

        $wpdb->delete( self::questions_table(), [ 'poll_id' => $poll_id ], [ '%d' ] );

        foreach ( array_values( $questions ) as $order => $text ) {
            $text = trim( $text );
            if ( '' === $text ) {
                continue;
            }
            $wpdb->insert(
                self::questions_table(),
                [
                    'poll_id'       => $poll_id,
                    'question_text' => sanitize_text_field( $text ),
                    'sort_order'    => $order,
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    /**
     * Get a single poll by ID (with questions).
     */
    public static function get( int $poll_id ): ?object {
        global $wpdb;

        $poll = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", self::polls_table(), $poll_id )
        );

        if ( ! $poll ) {
            return null;
        }

        $poll->questions = self::get_questions( $poll_id );

        return $poll;
    }

    /**
     * Get questions for a poll.
     *
     * @return object[]
     */
    public static function get_questions( int $poll_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE poll_id = %d ORDER BY sort_order ASC",
                self::questions_table(),
                $poll_id
            )
        );
    }

    /**
     * Get all polls, optionally filtered.
     *
     * @param array{status?: string, orderby?: string, order?: string, limit?: int, offset?: int} $args
     * @return object[]
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;

        $where   = '1=1';
        $params  = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $orderby = in_array( $args['orderby'] ?? '', [ 'title', 'start_date', 'end_date', 'created_at', 'status' ], true )
            ? $args['orderby']
            : 'created_at';
        $order = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM %i WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params = array_merge( [ self::polls_table() ], $params, [ $limit, $offset ] );

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Count polls, optionally filtered by status.
     */
    public static function count( ?string $status = null ): int {
        global $wpdb;

        if ( $status ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s", self::polls_table(), $status )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM %i", self::polls_table() )
        );
    }

    /**
     * Delete a poll and its questions and votes.
     */
    public static function delete( int $poll_id ): bool {
        global $wpdb;

        $wpdb->delete( $wpdb->prefix . 'evoting_votes', [ 'poll_id' => $poll_id ], [ '%d' ] );
        $wpdb->delete( self::questions_table(), [ 'poll_id' => $poll_id ], [ '%d' ] );

        return (bool) $wpdb->delete( self::polls_table(), [ 'id' => $poll_id ], [ '%d' ] );
    }

    /**
     * Check if poll is currently active (open and within date range).
     */
    public static function is_active( object $poll ): bool {
        if ( 'open' !== $poll->status ) {
            return false;
        }

        $now = current_time( 'mysql' );

        return $now >= $poll->start_date && $now <= $poll->end_date;
    }

    /**
     * Check if poll has ended.
     */
    public static function is_ended( object $poll ): bool {
        if ( 'closed' === $poll->status ) {
            return true;
        }

        return current_time( 'mysql' ) > $poll->end_date;
    }
}
