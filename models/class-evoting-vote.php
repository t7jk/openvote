<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Vote {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_votes';
    }

    /**
     * Check if a user is eligible to vote in a poll.
     * Delegates to Evoting_Eligibility::can_vote_or_error() (7 checks).
     *
     * @return true|\WP_Error
     */
    public static function is_eligible( int $poll_id, int $user_id ): true|\WP_Error {
        return Evoting_Eligibility::can_vote_or_error( $user_id, $poll_id );
    }

    /**
     * Count users eligible to vote in a poll.
     * Builds the query dynamically based on the field map configuration.
     */
    public static function get_eligible_count( object $poll ): int {
        global $wpdb;

        $map    = Evoting_Field_Map::get();
        $joins  = '';
        $where  = [ '1=1' ];
        $params = [];

        // --- Email ---
        $email_key = $map['email'] ?? 'user_email';
        if ( Evoting_Field_Map::is_core_field( $email_key ) ) {
            $where[] = "u.{$email_key} != ''";
        } else {
            $key_safe = sanitize_key( $email_key );
            $joins   .= " INNER JOIN {$wpdb->usermeta} um_email"
                      . " ON u.ID = um_email.user_id AND um_email.meta_key = %s AND um_email.meta_value != ''";
            $params[] = $key_safe;
        }

        // --- Meta fields: nickname, first_name, last_name, city ---
        $meta_fields = [
            'nickname'   => 'um_nick',
            'first_name' => 'um_fn',
            'last_name'  => 'um_ln',
            'city'       => 'um_city',
        ];

        foreach ( $meta_fields as $logical => $alias ) {
            $actual = sanitize_key( $map[ $logical ] ?? $logical );
            if ( Evoting_Field_Map::is_core_field( $actual ) ) {
                $where[] = "u.{$actual} != ''";
            } else {
                $joins   .= " INNER JOIN {$wpdb->usermeta} {$alias}"
                          . " ON u.ID = {$alias}.user_id AND {$alias}.meta_key = %s AND {$alias}.meta_value != ''";
                $params[] = $actual;
            }
        }

        // --- Group filter via wp_evoting_group_members ---
        $group_ids = Evoting_Poll::get_target_group_ids( $poll );
        if ( ! empty( $group_ids ) ) {
            $gm_table     = $wpdb->prefix . 'evoting_group_members';
            $g_holders    = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
            $joins       .= " INNER JOIN {$gm_table} gm ON u.ID = gm.user_id AND gm.group_id IN ({$g_holders})";
            $params       = array_merge( $params, $group_ids );
        }

        $sql = "SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                {$joins}
                WHERE " . implode( ' AND ', $where );

        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Cast votes for all questions in a poll.
     *
     * @param int                $poll_id
     * @param int                $user_id
     * @param array<int, int>    $answers  question_id => answer_id
     * @param bool               $is_anonymous
     * @return true|\WP_Error
     */
    public static function cast( int $poll_id, int $user_id, array $answers, bool $is_anonymous = false ): true|\WP_Error {
        global $wpdb;

        $poll = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new \WP_Error( 'poll_not_found', __( 'Głosowanie nie zostało znalezione.', 'evoting' ), [ 'status' => 404 ] );
        }

        if ( ! Evoting_Poll::is_active( $poll ) ) {
            return new \WP_Error( 'poll_not_active', __( 'Głosowanie nie jest aktywne.', 'evoting' ), [ 'status' => 403 ] );
        }

        $eligible = self::is_eligible( $poll_id, $user_id );
        if ( is_wp_error( $eligible ) ) {
            return $eligible;
        }

        if ( self::has_voted( $poll_id, $user_id ) ) {
            return new \WP_Error( 'already_voted', __( 'Już oddałeś głos w tym głosowaniu.', 'evoting' ), [ 'status' => 403 ] );
        }

        $question_ids = array_map( 'intval', array_column( $poll->questions, 'id' ) );

        // Ensure all questions answered.
        foreach ( $question_ids as $qid ) {
            if ( ! isset( $answers[ $qid ] ) ) {
                return new \WP_Error( 'missing_answer', __( 'Odpowiedz na wszystkie pytania.', 'evoting' ), [ 'status' => 400 ] );
            }
        }

        $answers_table = $wpdb->prefix . 'evoting_answers';

        foreach ( $answers as $question_id => $answer_id ) {
            $question_id = (int) $question_id;
            $answer_id   = (int) $answer_id;

            if ( ! in_array( $question_id, $question_ids, true ) ) {
                return new \WP_Error( 'invalid_question', __( 'Nieprawidłowe pytanie.', 'evoting' ), [ 'status' => 400 ] );
            }

            // Verify answer belongs to this question.
            $valid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM %i WHERE id = %d AND question_id = %d",
                    $answers_table,
                    $answer_id,
                    $question_id
                )
            );

            if ( ! $valid ) {
                return new \WP_Error( 'invalid_answer', __( 'Nieprawidłowa odpowiedź.', 'evoting' ), [ 'status' => 400 ] );
            }

            $inserted = $wpdb->insert(
                self::table(),
                [
                    'poll_id'      => $poll_id,
                    'question_id'  => $question_id,
                    'user_id'      => $user_id,
                    'answer_id'    => $answer_id,
                    'is_anonymous' => $is_anonymous ? 1 : 0,
                    'voted_at'     => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%d', '%d', '%d', '%s' ]
            );

            if ( $inserted === false ) {
                if ( $wpdb->last_error && strpos( $wpdb->last_error, 'Duplicate' ) !== false ) {
                    return new \WP_Error( 'already_voted', __( 'Już oddałeś głos w tym głosowaniu.', 'evoting' ), [ 'status' => 403 ] );
                }
                return new \WP_Error( 'vote_failed', __( 'Nie udało się zapisać głosu. Spróbuj ponownie.', 'evoting' ), [ 'status' => 500 ] );
            }
        }

        return true;
    }

    /**
     * Check if a user has already voted in a poll.
     */
    public static function has_voted( int $poll_id, int $user_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE poll_id = %d AND user_id = %d",
                self::table(),
                $poll_id,
                $user_id
            )
        );
    }

    /**
     * Get results for a poll (dynamic answers, non-voters counted as abstain).
     */
    public static function get_results( int $poll_id ): array {
        global $wpdb;

        $poll = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return [ 'total_eligible' => 0, 'total_voters' => 0, 'questions' => [] ];
        }

        $total_eligible = self::get_eligible_count( $poll );

        $total_voters = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM %i WHERE poll_id = %d",
                self::table(),
                $poll_id
            )
        );

        $non_voters = max( 0, $total_eligible - $total_voters );
        $questions  = [];

        foreach ( $poll->questions as $question ) {
            $counts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT answer_id, COUNT(*) as cnt FROM %i WHERE poll_id = %d AND question_id = %d GROUP BY answer_id",
                    self::table(),
                    $poll_id,
                    $question->id
                )
            );

            $count_map = [];
            foreach ( $counts as $row ) {
                $count_map[ (int) $row->answer_id ] = (int) $row->cnt;
            }

            $answers     = [];
            $total_votes = 0;

            foreach ( $question->answers as $answer ) {
                $cnt          = $count_map[ (int) $answer->id ] ?? 0;
                $answers[]    = [
                    'id'         => (int) $answer->id,
                    'text'       => $answer->body,
                    'is_abstain' => (bool) $answer->is_abstain,
                    'count'      => $cnt,
                ];
                $total_votes += $cnt;
            }

            // Percentages relative to actual votes cast (non-voters are NOT counted).
            foreach ( $answers as &$a ) {
                $a['pct'] = $total_votes > 0 ? round( $a['count'] / $total_votes * 100, 1 ) : 0.0;
            }
            unset( $a );

            $questions[] = [
                'question_id'   => (int) $question->id,
                'question_text' => $question->body,
                'answers'       => $answers,
                'total'         => $total_votes,
            ];
        }

        return [
            'total_eligible' => $total_eligible,
            'total_voters'   => $total_voters,
            'non_voters'     => $non_voters,
            'questions'      => $questions,
        ];
    }

    /**
     * Get voter list for public results — one entry per voter.
     * Each voter chose jawnie or anonimowo; anonymous voters shown as "Anonimowy".
     *
     * @param int $poll_id
     * @param int $limit  0 = no limit.
     * @param int $offset
     * @return array<int, array{nicename: string}>
     */
    public static function get_voters_anonymous( int $poll_id, int $limit = 0, int $offset = 0 ): array {
        global $wpdb;

        $sql = "SELECT v.user_id, MAX(v.is_anonymous) AS is_anon, u.user_nicename
                 FROM " . self::table() . " v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 WHERE v.poll_id = %d
                 GROUP BY v.user_id, u.user_nicename
                 ORDER BY u.user_nicename ASC";
        $params = [ $poll_id ];
        if ( $limit > 0 ) {
            $sql   .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
        }
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

        $voters = [];
        foreach ( $rows as $row ) {
            $voters[] = [
                'nicename' => (int) $row->is_anon
                    ? __( 'Anonimowy', 'evoting' )
                    : self::anonymize_nicename( $row->user_nicename ),
            ];
        }

        return $voters;
    }

    /**
     * Returns list of eligible users who did NOT vote in the given poll.
     * Each entry contains anonymized nicename (same rules as voters list).
     *
     * @param int $poll_id
     * @param int $limit  0 = no limit (e.g. for PDF export).
     * @param int $offset
     * @return array<int, array{nicename: string}>
     */
    public static function get_non_voters( int $poll_id, int $limit = 0, int $offset = 0 ): array {
        global $wpdb;

        $poll = Evoting_Poll::get( $poll_id );
        if ( ! $poll ) {
            return [];
        }

        $map    = Evoting_Field_Map::get();
        $joins  = '';
        $where  = [ '1=1' ];
        $params = [];

        // Email field.
        $email_key = $map['email'] ?? 'user_email';
        if ( Evoting_Field_Map::is_core_field( $email_key ) ) {
            $where[] = "u.{$email_key} != ''";
        } else {
            $key_safe = sanitize_key( $email_key );
            $joins   .= " INNER JOIN {$wpdb->usermeta} um_email"
                      . " ON u.ID = um_email.user_id AND um_email.meta_key = %s AND um_email.meta_value != ''";
            $params[] = $key_safe;
        }

        // Meta fields: nickname, first_name, last_name, city.
        $meta_fields = [
            'nickname'   => 'um_nick',
            'first_name' => 'um_fn',
            'last_name'  => 'um_ln',
            'city'       => 'um_city',
        ];
        foreach ( $meta_fields as $logical => $alias ) {
            $actual = sanitize_key( $map[ $logical ] ?? $logical );
            if ( Evoting_Field_Map::is_core_field( $actual ) ) {
                $where[] = "u.{$actual} != ''";
            } else {
                $joins   .= " INNER JOIN {$wpdb->usermeta} {$alias}"
                          . " ON u.ID = {$alias}.user_id AND {$alias}.meta_key = %s AND {$alias}.meta_value != ''";
                $params[] = $actual;
            }
        }

        // Group filter.
        $group_ids = Evoting_Poll::get_target_group_ids( $poll );
        if ( ! empty( $group_ids ) ) {
            $gm_table  = $wpdb->prefix . 'evoting_group_members';
            $holders   = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
            $joins    .= " INNER JOIN {$gm_table} gm ON u.ID = gm.user_id AND gm.group_id IN ({$holders})";
            $params    = array_merge( $params, $group_ids );
        }

        // Exclude users who have voted.
        $votes_table = self::table();
        $joins      .= " LEFT JOIN {$votes_table} vt"
                     . " ON u.ID = vt.user_id AND vt.poll_id = %d";
        $where[]     = 'vt.user_id IS NULL';
        $params[]    = $poll_id;

        $sql = "SELECT DISTINCT u.ID, u.user_nicename
                FROM {$wpdb->users} u
                {$joins}
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY u.user_nicename ASC";

        if ( $limit > 0 ) {
            $sql   .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
        }

        if ( ! empty( $params ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
        } else {
            $rows = $wpdb->get_results( $sql );
        }

        $result = [];
        foreach ( $rows as $row ) {
            $result[] = [ 'nicename' => self::anonymize_nicename( $row->user_nicename ) ];
        }

        return $result;
    }

    /**
     * Admin voter list — full name + anonymized email only.
     * GSM, location and other data are hidden even from admins.
     * Uses a single query with usermeta joins to avoid N+1 get_userdata() on large lists.
     *
     * @param int $poll_id
     * @param int $limit  0 = no limit (e.g. for PDF export).
     * @param int $offset
     * @return array<int, array{name: string, email_anon: string, voted_at: string}>
     */
    public static function get_voters_admin( int $poll_id, int $limit = 0, int $offset = 0 ): array {
        global $wpdb;

        $map       = Evoting_Field_Map::get();
        $fn_key    = sanitize_key( $map['first_name'] ?? 'first_name' );
        $ln_key    = sanitize_key( $map['last_name'] ?? 'last_name' );
        $fn_core   = Evoting_Field_Map::is_core_field( $map['first_name'] ?? 'first_name' );
        $ln_core   = Evoting_Field_Map::is_core_field( $map['last_name'] ?? 'last_name' );

        $fn_select = $fn_core ? "u.{$fn_key}" : 'um_fn.meta_value';
        $ln_select = $ln_core ? "u.{$ln_key}" : 'um_ln.meta_value';

        $joins = '';
        if ( ! $fn_core ) {
            $joins .= " LEFT JOIN {$wpdb->usermeta} um_fn ON u.ID = um_fn.user_id AND um_fn.meta_key = '" . esc_sql( $fn_key ) . "'";
        }
        if ( ! $ln_core ) {
            $joins .= " LEFT JOIN {$wpdb->usermeta} um_ln ON u.ID = um_ln.user_id AND um_ln.meta_key = '" . esc_sql( $ln_key ) . "'";
        }

        $group_by = 'v.user_id, u.user_email';
        if ( ! $fn_core ) {
            $group_by .= ', um_fn.meta_value';
        }
        if ( ! $ln_core ) {
            $group_by .= ', um_ln.meta_value';
        }
        $sql = "SELECT v.user_id, MAX(v.is_anonymous) AS is_anon, MIN(v.voted_at) AS voted_at, u.user_email, {$fn_select} AS first_name, {$ln_select} AS last_name
                 FROM " . self::table() . " v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 {$joins}
                 WHERE v.poll_id = %d
                 GROUP BY {$group_by}
                 ORDER BY voted_at ASC";

        $params = [ $poll_id ];
        if ( $limit > 0 ) {
            $sql   .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
        }

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

        $voters = [];
        foreach ( $rows as $row ) {
            $is_anon = (int) $row->is_anon;
            if ( $is_anon ) {
                $voters[] = [
                    'name'       => __( 'Anonimowy', 'evoting' ),
                    'email_anon' => '—',
                    'voted_at'   => $row->voted_at,
                ];
                continue;
            }

            $first_name = isset( $row->first_name ) ? trim( (string) $row->first_name ) : '';
            $last_name  = isset( $row->last_name ) ? trim( (string) $row->last_name ) : '';
            $name       = trim( $first_name . ' ' . $last_name );
            if ( '' === $name ) {
                $name = __( '(brak imienia i nazwiska)', 'evoting' );
            }

            $voters[] = [
                'name'       => $name,
                'email_anon' => self::anonymize_email( $row->user_email ?? '' ),
                'voted_at'   => $row->voted_at,
            ];
        }

        return $voters;
    }

    /**
     * "Jan Kowalski" → "Jan...ski"
     */
    public static function anonymize_nicename( string $value ): string {
        $len = mb_strlen( $value );
        if ( $len <= 6 ) {
            return str_repeat( '.', $len );
        }
        return mb_substr( $value, 0, 3 )
             . '...'
             . mb_substr( $value, -3 );
    }

    /**
     * Kropkowanie email: każdy człon (segment przed/po kropce w loginie, segmenty w domenie)
     * ma tylko pierwsze 2 litery, potem tyle kropek ile reszta znaków.
     * Np. Janusz.Kowalski@uniwersytet.edu.pl → ja.....Ko.....@un........ed..pl
     */
    public static function anonymize_email( string $email ): string {
        if ( ! str_contains( $email, '@' ) ) {
            return str_repeat( '*', mb_strlen( $email ) );
        }

        [ $local, $domain ] = explode( '@', $email, 2 );

        $mask_segment = function ( string $segment ): string {
            $len = mb_strlen( $segment );
            if ( $len <= 2 ) {
                return $segment;
            }
            return mb_substr( $segment, 0, 2 ) . str_repeat( '.', $len - 2 );
        };

        $local_parts  = explode( '.', $local );
        $local_anon   = implode( '.', array_map( $mask_segment, $local_parts ) );

        $domain_parts = explode( '.', $domain );
        $domain_anon  = implode( '.', array_map( $mask_segment, $domain_parts ) );

        return $local_anon . '@' . $domain_anon;
    }
}
