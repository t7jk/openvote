<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Vote {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_votes';
    }

    /**
     * Check if a user is eligible to vote in a poll.
     * Requires complete profile (per field map) + matching target group if set.
     *
     * @return true|\WP_Error
     */
    public static function is_eligible( int $poll_id, int $user_id ): true|\WP_Error {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return new \WP_Error( 'no_user', __( 'Użytkownik nie istnieje.', 'evoting' ), [ 'status' => 403 ] );
        }

        // Check all 5 required fields via the field map.
        $required = [
            'first_name' => Evoting_Field_Map::LABELS['first_name'],
            'last_name'  => Evoting_Field_Map::LABELS['last_name'],
            'nickname'   => Evoting_Field_Map::LABELS['nickname'],
            'email'      => Evoting_Field_Map::LABELS['email'],
            'city'       => Evoting_Field_Map::LABELS['city'],
        ];

        foreach ( $required as $logical => $label ) {
            $value = Evoting_Field_Map::get_user_value( $user, $logical );
            if ( '' === $value ) {
                return new \WP_Error(
                    'incomplete_profile',
                    sprintf(
                        /* translators: %s: field label */
                        __( 'Twój profil jest niekompletny. Brakuje: %s.', 'evoting' ),
                        $label
                    ),
                    [ 'status' => 403 ]
                );
            }
        }

        $poll = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return new \WP_Error( 'poll_not_found', __( 'Głosowanie nie zostało znalezione.', 'evoting' ), [ 'status' => 404 ] );
        }

        // Group restriction.
        if ( 'group' === $poll->target_type && ! empty( $poll->target_group ) ) {
            $location = Evoting_Field_Map::get_user_value( $user, 'city' );
            if ( $location !== $poll->target_group ) {
                return new \WP_Error(
                    'wrong_group',
                    sprintf(
                        /* translators: %s: group name */
                        __( 'To głosowanie jest dostępne tylko dla grupy: %s.', 'evoting' ),
                        $poll->target_group
                    ),
                    [ 'status' => 403 ]
                );
            }
        }

        return true;
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

        // --- Group filter on city ---
        if ( 'group' === $poll->target_type && ! empty( $poll->target_group ) ) {
            $city_key = sanitize_key( $map['city'] ?? 'user_registration_miejsce_spotkania' );
            if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
                $where[]  = "u.{$city_key} = %s";
                $params[] = $poll->target_group;
            } else {
                // um_city JOIN already exists; just filter its value.
                $where[]  = 'um_city.meta_value = %s';
                $params[] = $poll->target_group;
            }
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
     * @return true|\WP_Error
     */
    public static function cast( int $poll_id, int $user_id, array $answers ): true|\WP_Error {
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
                    'poll_id'     => $poll_id,
                    'question_id' => $question_id,
                    'user_id'     => $user_id,
                    'answer_id'   => $answer_id,
                    'voted_at'    => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%d', '%d', '%s' ]
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
                    'text'       => $answer->answer_text,
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
                'question_text' => $question->question_text,
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
     * Get anonymous voter list — anonymized user_nicename only.
     *
     * @return array<int, array{nicename: string}>
     */
    public static function get_voters_anonymous( int $poll_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT v.user_id, u.user_nicename
                 FROM %i v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 WHERE v.poll_id = %d
                 ORDER BY u.user_nicename ASC",
                self::table(),
                $poll_id
            )
        );

        $voters = [];
        foreach ( $rows as $row ) {
            $voters[] = [
                'nicename' => self::anonymize_nicename( $row->user_nicename ),
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
                "SELECT DISTINCT v.user_id, v.voted_at, u.user_email
                 FROM %i v
                 INNER JOIN {$wpdb->users} u ON v.user_id = u.ID
                 WHERE v.poll_id = %d
                 ORDER BY v.voted_at ASC",
                self::table(),
                $poll_id
            )
        );

        $voters = [];
        foreach ( $rows as $row ) {
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
     * Anonymize a display name / nicename.
     * Keeps first 3 and last 3 characters, replaces the middle with dots.
     * Example: "Januszek" → "Jan..zek"
     */
    private static function anonymize_nicename( string $value ): string {
        $len = mb_strlen( $value );
        if ( $len <= 6 ) {
            return $value;
        }
        return mb_substr( $value, 0, 3 )
             . str_repeat( '.', $len - 6 )
             . mb_substr( $value, -3 );
    }

    /**
     * Anonymize an email address.
     * Local part: keep first 3 chars, replace rest with dots.
     * Domain:     keep first char + dots + TLD.
     * Example: "jan-kowalski@gmail.com" → "jan.........@g....com"
     */
    private static function anonymize_email( string $email ): string {
        if ( ! str_contains( $email, '@' ) ) {
            return str_repeat( '*', mb_strlen( $email ) );
        }

        [ $local, $domain ] = explode( '@', $email, 2 );

        // Local part.
        $local_len  = mb_strlen( $local );
        $local_anon = mb_substr( $local, 0, min( 3, $local_len ) )
                    . str_repeat( '.', max( 0, $local_len - 3 ) );

        // Domain: split at last dot to get TLD.
        $dot_pos     = strrpos( $domain, '.' );
        $domain_name = $dot_pos !== false ? substr( $domain, 0, $dot_pos ) : $domain;
        $tld         = $dot_pos !== false ? substr( $domain, $dot_pos + 1 ) : '';
        $dn_len      = mb_strlen( $domain_name );
        $domain_anon = mb_substr( $domain_name, 0, 1 )
                     . str_repeat( '.', max( 0, $dn_len - 1 ) )
                     . $tld;

        return $local_anon . '@' . $domain_anon;
    }
}
