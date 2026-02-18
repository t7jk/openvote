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

    private static function answers_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_answers';
    }

    /**
     * Create a new poll with questions and answers.
     *
     * @param array{
     *     title: string,
     *     description?: string,
     *     status?: string,
     *     start_date: string,
     *     end_date: string,
     *     target_type?: string,
     *     target_group?: string,
     *     notify_users?: bool,
     *     questions: array<array{text: string, answers: string[]}>
     * } $data
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
                'target_type'  => $data['target_type'] ?? 'all',
                'target_group' => ( isset( $data['target_group'] ) && '' !== $data['target_group'] ) ? sanitize_text_field( $data['target_group'] ) : null,
                'notify_users' => ! empty( $data['notify_users'] ) ? 1 : 0,
                'created_by'   => get_current_user_id(),
                'created_at'   => current_time( 'mysql' ),
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
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
        if ( isset( $data['target_type'] ) ) {
            $update['target_type'] = sanitize_text_field( $data['target_type'] );
            $format[]              = '%s';
        }
        if ( array_key_exists( 'target_group', $data ) ) {
            $update['target_group'] = ( '' !== $data['target_group'] ) ? sanitize_text_field( $data['target_group'] ) : null;
            $format[]               = '%s';
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
     * Save questions (with answers) for a poll (replace all).
     *
     * @param int   $poll_id
     * @param array<array{text: string, answers: string[]}> $questions
     */
    public static function save_questions( int $poll_id, array $questions ): void {
        global $wpdb;

        // Cascade delete existing answers first, then questions.
        $existing_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM %i WHERE poll_id = %d", self::questions_table(), $poll_id )
        );
        if ( $existing_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $existing_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM %i WHERE question_id IN ({$placeholders})",
                    array_merge( [ self::answers_table() ], $existing_ids )
                )
            );
        }

        $wpdb->delete( self::questions_table(), [ 'poll_id' => $poll_id ], [ '%d' ] );

        foreach ( array_values( $questions ) as $q_order => $q ) {
            $text = trim( $q['text'] ?? '' );
            if ( '' === $text ) {
                continue;
            }

            $wpdb->insert(
                self::questions_table(),
                [
                    'poll_id'       => $poll_id,
                    'question_text' => sanitize_text_field( $text ),
                    'sort_order'    => $q_order,
                ],
                [ '%d', '%s', '%d' ]
            );

            $question_id = (int) $wpdb->insert_id;

            if ( ! empty( $q['answers'] ) ) {
                self::save_answers( $question_id, $q['answers'] );
            }
        }
    }

    /**
     * Save answers for a question.
     *
     * @param int      $question_id
     * @param string[] $answers  Last item is always is_abstain=1.
     */
    public static function save_answers( int $question_id, array $answers ): void {
        global $wpdb;

        $wpdb->delete( self::answers_table(), [ 'question_id' => $question_id ], [ '%d' ] );

        $answers = array_values( $answers );
        $total   = count( $answers );

        foreach ( $answers as $a_order => $text ) {
            $text = trim( $text );
            if ( '' === $text ) {
                continue;
            }
            $is_abstain = ( $a_order === $total - 1 ) ? 1 : 0;

            $wpdb->insert(
                self::answers_table(),
                [
                    'question_id' => $question_id,
                    'answer_text' => sanitize_text_field( $text ),
                    'sort_order'  => $a_order,
                    'is_abstain'  => $is_abstain,
                ],
                [ '%d', '%s', '%d', '%d' ]
            );
        }
    }

    /**
     * Get answers for a question.
     *
     * @return object[]
     */
    public static function get_answers( int $question_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE question_id = %d ORDER BY sort_order ASC",
                self::answers_table(),
                $question_id
            )
        );
    }

    /**
     * Get a single poll by ID (with questions and answers).
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
     * Get questions for a poll (each with answers).
     *
     * @return object[]
     */
    public static function get_questions( int $poll_id ): array {
        global $wpdb;

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE poll_id = %d ORDER BY sort_order ASC",
                self::questions_table(),
                $poll_id
            )
        );

        foreach ( $questions as $question ) {
            $question->answers = self::get_answers( (int) $question->id );
        }

        return $questions;
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

        $sql    = "SELECT * FROM %i WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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
     * Delete a poll and all related data.
     */
    public static function delete( int $poll_id ): bool {
        global $wpdb;

        // Delete answers → votes → questions → poll.
        $question_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM %i WHERE poll_id = %d", self::questions_table(), $poll_id )
        );
        if ( $question_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM %i WHERE question_id IN ({$placeholders})",
                    array_merge( [ self::answers_table() ], $question_ids )
                )
            );
        }

        $wpdb->delete( $wpdb->prefix . 'evoting_votes', [ 'poll_id' => $poll_id ], [ '%d' ] );
        $wpdb->delete( self::questions_table(), [ 'poll_id' => $poll_id ], [ '%d' ] );

        return (bool) $wpdb->delete( self::polls_table(), [ 'id' => $poll_id ], [ '%d' ] );
    }

    /**
     * Check if poll is currently active.
     */
    public static function is_active( object $poll ): bool {
        if ( 'open' !== $poll->status ) {
            return false;
        }

        $today = current_time( 'Y-m-d' );

        return $today >= $poll->start_date && $today <= $poll->end_date;
    }

    /**
     * Check if poll has ended.
     */
    public static function is_ended( object $poll ): bool {
        if ( 'closed' === $poll->status ) {
            return true;
        }

        return current_time( 'Y-m-d' ) > $poll->end_date;
    }

    /**
     * Get distinct location groups from user meta (for target audience dropdown).
     *
     * @return string[]
     */
    public static function get_location_groups(): array {
        global $wpdb;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
                 WHERE meta_key = %s AND meta_value != ''
                 ORDER BY meta_value ASC",
                'user_registration_miejsce_spotkania'
            )
        );

        return array_filter( $rows );
    }
}
