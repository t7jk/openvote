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
     *     date_start: string,
     *     date_end: string,
     *     join_mode?: string,
     *     vote_mode?: string,
     *     target_groups?: string,
     *     notify_start?: bool,
     *     notify_end?: bool,
     *     questions: array<array{text: string, answers: string[]}>
     * } $data
     * @return int|false Poll ID or false on failure.
     */
    public static function create( array $data ): int|false {
        global $wpdb;

        $result = $wpdb->insert(
            self::polls_table(),
            [
                'title'         => sanitize_text_field( $data['title'] ),
                'description'   => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
                'status'        => in_array( $data['status'] ?? 'draft', [ 'draft', 'open', 'closed' ], true ) ? $data['status'] : 'draft',
                'join_mode'     => in_array( $data['join_mode'] ?? 'open', [ 'open', 'closed' ], true ) ? $data['join_mode'] : 'open',
                'vote_mode'     => in_array( $data['vote_mode'] ?? 'public', [ 'public', 'anonymous' ], true ) ? $data['vote_mode'] : 'public',
                'target_groups' => isset( $data['target_groups'] ) && '' !== $data['target_groups'] ? sanitize_text_field( $data['target_groups'] ) : null,
                'notify_start'  => ! empty( $data['notify_start'] ) ? 1 : 0,
                'notify_end'    => ! empty( $data['notify_end'] ) ? 1 : 0,
                'date_start'    => $data['date_start'],
                'date_end'      => $data['date_end'],
                'created_by'    => get_current_user_id(),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' ]
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
        if ( isset( $data['join_mode'] ) ) {
            $update['join_mode'] = in_array( $data['join_mode'], [ 'open', 'closed' ], true ) ? $data['join_mode'] : 'open';
            $format[]            = '%s';
        }
        if ( isset( $data['vote_mode'] ) ) {
            $update['vote_mode'] = in_array( $data['vote_mode'], [ 'public', 'anonymous' ], true ) ? $data['vote_mode'] : 'public';
            $format[]            = '%s';
        }
        if ( array_key_exists( 'target_groups', $data ) ) {
            $update['target_groups'] = ( '' !== $data['target_groups'] ) ? sanitize_text_field( $data['target_groups'] ) : null;
            $format[]                = '%s';
        }
        if ( isset( $data['notify_start'] ) ) {
            $update['notify_start'] = ! empty( $data['notify_start'] ) ? 1 : 0;
            $format[]               = '%d';
        }
        if ( isset( $data['notify_end'] ) ) {
            $update['notify_end'] = ! empty( $data['notify_end'] ) ? 1 : 0;
            $format[]             = '%d';
        }
        if ( isset( $data['date_start'] ) ) {
            $update['date_start'] = $data['date_start'];
            $format[]             = '%s';
        }
        if ( isset( $data['date_end'] ) ) {
            $update['date_end'] = $data['date_end'];
            $format[]           = '%s';
        }

        if ( empty( $update ) && ! isset( $data['questions'] ) ) {
            return true;
        }

        if ( ! empty( $update ) ) {
            $result = $wpdb->update( self::polls_table(), $update, [ 'id' => $poll_id ], $format, [ '%d' ] );
            if ( false === $result ) {
                return false;
            }
        }

        if ( isset( $data['questions'] ) ) {
            self::save_questions( $poll_id, $data['questions'] );
        }

        return true;
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
                    'poll_id'    => $poll_id,
                    'body'       => sanitize_text_field( $text ),
                    'sort_order' => $q_order,
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
                    'body'        => sanitize_text_field( $text ),
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

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $orderby = in_array( $args['orderby'] ?? '', [ 'title', 'date_start', 'date_end', 'created_at', 'status' ], true )
            ? $args['orderby']
            : 'created_at';
        $order   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

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
     * Duplicate a poll: new poll with status draft, date_start = today, date_end = original date_end.
     * Questions and answers are copied. No votes.
     *
     * @return int|false New poll ID or false on failure.
     */
    public static function duplicate( int $poll_id ): int|false {
        $poll = self::get( $poll_id );
        if ( ! $poll || empty( $poll->questions ) ) {
            return false;
        }

        $today_date = current_time( 'Y-m-d' );
        $today_start = $today_date . ' 00:00:00';

        $questions = [];
        foreach ( $poll->questions as $q ) {
            $answers = [];
            if ( ! empty( $q->answers ) ) {
                foreach ( $q->answers as $a ) {
                    $answers[] = $a->body;
                }
            }
            if ( count( $answers ) < 3 ) {
                continue;
            }
            $questions[] = [
                'text'    => $q->body,
                'answers' => $answers,
            ];
        }

        if ( empty( $questions ) ) {
            return false;
        }

        $data = [
            'title'         => $poll->title,
            'description'   => $poll->description ?? '',
            'status'        => 'draft',
            'date_start'    => $today_start,
            'date_end'      => $poll->date_end ?: ( $today_date . ' 23:59:59' ),
            'join_mode'     => $poll->join_mode ?? 'open',
            'vote_mode'     => $poll->vote_mode ?? 'public',
            'target_groups' => $poll->target_groups ?? '',
            'notify_start'  => false,
            'notify_end'    => false,
            'questions'     => $questions,
        ];

        return self::create( $data );
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

        $now = current_time( 'Y-m-d H:i:s' );
        $start = $poll->date_start;
        $end   = $poll->date_end;
        if ( strlen( $start ) === 10 ) {
            $start .= ' 00:00:00';
        }
        if ( strlen( $end ) === 10 ) {
            $end .= ' 23:59:59';
        }

        return $now >= $start && $now <= $end;
    }

    /**
     * Check if poll has ended.
     */
    public static function is_ended( object $poll ): bool {
        $end = $poll->date_end;
        if ( strlen( $end ) === 10 ) {
            $end .= ' 23:59:59';
        }
        if ( 'closed' === $poll->status ) {
            return true;
        }

        return current_time( 'Y-m-d H:i:s' ) > $end;
    }

    /**
     * Get distinct location groups from user meta (for target audience dropdown).
     *
     * @return string[]
     */
    public static function get_location_groups(): array {
        global $wpdb;

        $city_key = Evoting_Field_Map::get_field( 'city' );

        if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
            $rows = $wpdb->get_col(
                "SELECT DISTINCT {$city_key}
                 FROM {$wpdb->users}
                 WHERE {$city_key} != ''
                 ORDER BY {$city_key} ASC"
            );
        } else {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
                     WHERE meta_key = %s AND meta_value != ''
                     ORDER BY meta_value ASC",
                    sanitize_key( $city_key )
                )
            );
        }

        return array_filter( $rows );
    }

    /**
     * Return target_groups as an array of group IDs.
     */
    public static function get_target_group_ids( object $poll ): array {
        if ( empty( $poll->target_groups ) ) {
            return [];
        }
        $decoded = json_decode( $poll->target_groups, true );
        if ( is_array( $decoded ) ) {
            return array_map( 'absint', $decoded );
        }
        return [];
    }
}
