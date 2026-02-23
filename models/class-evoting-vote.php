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

            $wpdb->insert(
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
            $abstain_idx = null;

            foreach ( $question->answers as $idx => $answer ) {
                $cnt = $count_map[ (int) $answer->id ] ?? 0;
                if ( $answer->is_abstain ) {
                    $abstain_idx = $idx;
                }
                $answers[] = [
                    'id'         => (int) $answer->id,
                    'text'       => $answer->body,
                    'is_abstain' => (bool) $answer->is_abstain,
                    'count'      => $cnt,
                ];
                $total_votes += $cnt;
            }

            // Add non-voters to the abstain answer.
            if ( null !== $abstain_idx ) {
                $answers[ $abstain_idx ]['count'] += $non_voters;
                $total_votes                      += $non_voters;
            }

            // Add percentages.
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
     * @return array<int, array{nicename: string}>
     */
    public static function get_voters_anonymous( int $poll_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.user_id, MAX(v.is_anonymous) AS is_anon, u.user_nicename
                 FROM %i v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 WHERE v.poll_id = %d
                 GROUP BY v.user_id, u.user_nicename
                 ORDER BY u.user_nicename ASC",
                self::table(),
                $poll_id
            )
        );

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
     * Admin voter list — full name + anonymized email only.
     * GSM, location and other data are hidden even from admins.
     *
     * @return array<int, array{name: string, email_anon: string, voted_at: string}>
     */
    public static function get_voters_admin( int $poll_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.user_id, MAX(v.is_anonymous) AS is_anon, MIN(v.voted_at) AS voted_at, u.user_email
                 FROM %i v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 WHERE v.poll_id = %d
                 GROUP BY v.user_id, u.user_email
                 ORDER BY voted_at ASC",
                self::table(),
                $poll_id
            )
        );

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

            $user = get_userdata( (int) $row->user_id );
            if ( ! $user ) {
                continue;
            }

            $first_name = Evoting_Field_Map::get_user_value( $user, 'first_name' );
            $last_name  = Evoting_Field_Map::get_user_value( $user, 'last_name' );

            $voters[] = [
                'name'       => trim( $first_name . ' ' . $last_name ),
                'email_anon' => self::anonymize_email( $row->user_email ),
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
     * "jan@gmail.com" → "jan.........@g....com"
     */
    public static function anonymize_email( string $email ): string {
        if ( ! str_contains( $email, '@' ) ) {
            return str_repeat( '*', mb_strlen( $email ) );
        }

        [ $local, $domain ] = explode( '@', $email, 2 );

        $local_anon = mb_substr( $local, 0, min( 3, mb_strlen( $local ) ) )
                    . '.........' ;

        $dot_pos     = strrpos( $domain, '.' );
        $domain_name = $dot_pos !== false ? substr( $domain, 0, $dot_pos ) : $domain;
        $tld         = $dot_pos !== false ? substr( $domain, $dot_pos + 1 ) : '';
        $domain_anon = mb_substr( $domain_name, 0, 1 ) . '....' . '.' . $tld;

        return $local_anon . '@' . $domain_anon;
    }
}
