<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Poll {

    private const ALLOWED_STATUSES = [ 'draft', 'open', 'closed' ];

    private static function polls_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_polls';
    }

    private static function questions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_questions';
    }

    private static function answers_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_answers';
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
     *     target_groups?: string,
     *     notify_start?: bool,
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
                'status'        => in_array( $data['status'] ?? 'draft', self::ALLOWED_STATUSES, true ) ? $data['status'] : 'draft',
                'target_groups' => isset( $data['target_groups'] ) && '' !== $data['target_groups'] ? sanitize_text_field( $data['target_groups'] ) : null,
                'notify_start'  => ! empty( $data['notify_start'] ) ? 1 : 0,
                'date_start'    => $data['date_start'],
                'date_end'      => $data['date_end'],
                'created_by'    => get_current_user_id(),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' ]
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
            $status = sanitize_text_field( $data['status'] );
            if ( in_array( $status, self::ALLOWED_STATUSES, true ) ) {
                $update['status'] = $status;
                $format[]        = '%s';
            }
        }
        if ( array_key_exists( 'target_groups', $data ) ) {
            $update['target_groups'] = ( '' !== $data['target_groups'] ) ? sanitize_text_field( $data['target_groups'] ) : null;
            $format[]                = '%s';
        }
        if ( isset( $data['notify_start'] ) ) {
            $update['notify_start'] = ! empty( $data['notify_start'] ) ? 1 : 0;
            $format[]               = '%d';
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
     * Duplicate a poll: new poll with status draft, date_start = now, date_end = now + 7 days (jak przy „Dodaj nowe”).
     * Questions and answers are copied. No votes.
     *
     * @return int|false New poll ID or false on failure.
     */
    public static function duplicate( int $poll_id ): int|false {
        $poll = self::get( $poll_id );
        if ( ! $poll || empty( $poll->questions ) ) {
            return false;
        }

        $now_ts     = current_time( 'timestamp' );
        $date_start = current_time( 'Y-m-d H:i:s' );
        $date_end   = wp_date( 'Y-m-d H:i:s', $now_ts + 7 * DAY_IN_SECONDS );

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
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'target_groups' => $poll->target_groups ?? '',
            'notify_start'  => false,
            'questions'     => $questions,
        ];

        $new_id = self::create( $data );
        if ( $new_id ) {
            self::update( $new_id, [ 'date_start' => $date_start, 'date_end' => $date_end ] );
        }
        return $new_id;
    }

    /**
     * Delete a poll and all related data.
     */
    public static function delete( int $poll_id ): bool {
        global $wpdb;

        $eq_table = $wpdb->prefix . 'openvote_email_queue';
        $wpdb->delete( $eq_table, [ 'poll_id' => $poll_id ], [ '%d' ] );

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

        $wpdb->delete( $wpdb->prefix . 'openvote_votes', [ 'poll_id' => $poll_id ], [ '%d' ] );
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

        $now   = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
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

        return openvote_current_time_for_voting( 'Y-m-d H:i:s' ) > $end;
    }

    /**
     * Get distinct location groups from user meta (for target audience dropdown).
     *
     * @return string[]
     */
    public static function get_location_groups(): array {
        global $wpdb;

        $city_key = Openvote_Field_Map::get_field( 'city' );

        if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
            $safe_col = '`' . esc_sql( $city_key ) . '`';
            $rows     = $wpdb->get_col(
                "SELECT DISTINCT {$safe_col}
                 FROM {$wpdb->users}
                 WHERE {$safe_col} != ''
                 ORDER BY {$safe_col} ASC"
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

    /**
     * Remove a group ID from target_groups in all polls (e.g. after group is deleted).
     */
    public static function remove_group_from_all_polls( int $group_id ): void {
        global $wpdb;

        $polls = $wpdb->get_results( "SELECT id, target_groups FROM " . self::polls_table() );
        foreach ( $polls as $poll ) {
            $ids = self::get_target_group_ids( $poll );
            if ( ! in_array( $group_id, $ids, true ) ) {
                continue;
            }
            $new_ids = array_values( array_filter( $ids, fn( $id ) => (int) $id !== $group_id ) );
            $new_json = empty( $new_ids ) ? null : wp_json_encode( $new_ids );
            $wpdb->update(
                self::polls_table(),
                [ 'target_groups' => $new_json ],
                [ 'id' => $poll->id ],
                [ '%s' ],
                [ '%d' ]
            );
        }
    }

    /**
     * Check if user belongs to the poll's target groups.
     * If poll has no target groups, all users are considered "in group".
     *
     * @param int    $user_id
     * @param object $poll Poll object (with target_groups).
     * @return bool
     */
    public static function user_in_target_groups( int $user_id, object $poll ): bool {
        $group_ids = self::get_target_group_ids( $poll );
        if ( empty( $group_ids ) ) {
            return true;
        }
        if ( Openvote_Field_Map::is_city_disabled() ) {
            global $wpdb;
            $wszyscy_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}openvote_groups WHERE name = %s",
                    Openvote_Field_Map::WSZYSCY_NAME
                )
            );
            if ( $wszyscy_id && [ (int) $wszyscy_id ] === array_map( 'intval', $group_ids ) ) {
                return true;
            }
        }
        global $wpdb;
        $gm_table     = $wpdb->prefix . 'openvote_group_members';
        $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$gm_table}
                 WHERE user_id = %d AND group_id IN ({$placeholders})",
                array_merge( [ $user_id ], $group_ids )
            )
        );
    }

    /**
     * Get polls that are currently active (status=open, now between date_start and date_end).
     * Returns full poll objects with questions and answers.
     *
     * @return object[]
     */
    public static function get_active_polls(): array {
        global $wpdb;

        $now   = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        $table = self::polls_table();

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = 'open' AND date_start <= %s AND date_end >= %s ORDER BY date_end ASC",
                $now,
                $now
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $polls = [];
        foreach ( array_map( 'absint', $ids ) as $id ) {
            $poll = self::get( $id );
            if ( $poll ) {
                $polls[] = $poll;
            }
        }
        return $polls;
    }

    /**
     * Zwraca zakończone głosowania, do których użytkownik był uprawniony
     * (niezależnie od tego, czy głosował).
     *
     * @param int $user_id
     * @return object[] Pełne obiekty głosowań (closed lub po dacie końca).
     */
    public static function get_closed_polls_for_user( int $user_id ): array {
        global $wpdb;

        $now   = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        $table = self::polls_table();

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE (status = 'closed' OR date_end < %s) ORDER BY date_end DESC",
                $now
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $out = [];
        foreach ( array_map( 'absint', $ids ) as $id ) {
            $poll = self::get( $id );
            if ( $poll && self::user_in_target_groups( $user_id, $poll ) ) {
                $out[] = $poll;
            }
        }
        return $out;
    }

    /**
     * Get ended polls for which the user was in target group but did not vote.
     * Used to show "Głosowanie dobiegło końca dnia ..., zobacz wyniki."
     *
     * @param int $user_id
     * @return object[] Polls with at least id, title, date_end.
     */
    public static function get_ended_polls_eligible_not_voted( int $user_id ): array {
        global $wpdb;

        $now   = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        $table = self::polls_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, date_end FROM {$table} WHERE (status = 'closed' OR date_end < %s) ORDER BY date_end DESC",
                $now
            )
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $out = [];
        foreach ( $rows as $row ) {
            $poll = self::get( (int) $row->id );
            if ( ! $poll ) {
                continue;
            }
            if ( ! self::user_in_target_groups( $user_id, $poll ) ) {
                continue;
            }
            if ( Openvote_Vote::has_voted( (int) $poll->id, $user_id ) ) {
                continue;
            }
            $out[] = $poll;
        }
        return $out;
    }
}
