<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Vote {

    private const VALID_ANSWERS = [ 'za', 'przeciw', 'wstrzymuje_sie' ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'evoting_votes';
    }

    /**
     * Cast votes for all questions in a poll.
     *
     * @param int                    $poll_id
     * @param int                    $user_id
     * @param array<int, string>     $answers question_id => answer
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

        if ( self::has_voted( $poll_id, $user_id ) ) {
            return new \WP_Error( 'already_voted', __( 'Już oddałeś głos w tym głosowaniu.', 'evoting' ), [ 'status' => 403 ] );
        }

        $question_ids = array_column( $poll->questions, 'id' );

        foreach ( $answers as $question_id => $answer ) {
            $question_id = (int) $question_id;

            if ( ! in_array( $question_id, array_map( 'intval', $question_ids ), true ) ) {
                return new \WP_Error( 'invalid_question', __( 'Nieprawidłowe pytanie.', 'evoting' ), [ 'status' => 400 ] );
            }

            if ( ! in_array( $answer, self::VALID_ANSWERS, true ) ) {
                return new \WP_Error( 'invalid_answer', __( 'Nieprawidłowa odpowiedź.', 'evoting' ), [ 'status' => 400 ] );
            }
        }

        // Ensure all questions are answered.
        foreach ( $question_ids as $qid ) {
            if ( ! isset( $answers[ (int) $qid ] ) ) {
                return new \WP_Error( 'missing_answer', __( 'Odpowiedz na wszystkie pytania.', 'evoting' ), [ 'status' => 400 ] );
            }
        }

        foreach ( $answers as $question_id => $answer ) {
            $wpdb->insert(
                self::table(),
                [
                    'poll_id'     => $poll_id,
                    'question_id' => (int) $question_id,
                    'user_id'     => $user_id,
                    'answer'      => $answer,
                    'voted_at'    => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%d', '%s', '%s' ]
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
     * Get results for a poll.
     *
     * @return array{
     *     total_voters: int,
     *     questions: array<int, array{
     *         question_id: int,
     *         question_text: string,
     *         za: int,
     *         przeciw: int,
     *         wstrzymuje_sie: int,
     *         total: int
     *     }>
     * }
     */
    public static function get_results( int $poll_id ): array {
        global $wpdb;

        $poll = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return [ 'total_voters' => 0, 'questions' => [] ];
        }

        // Count distinct voters.
        $total_voters = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM %i WHERE poll_id = %d",
                self::table(),
                $poll_id
            )
        );

        $questions = [];

        foreach ( $poll->questions as $question ) {
            $counts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT answer, COUNT(*) as cnt FROM %i WHERE poll_id = %d AND question_id = %d GROUP BY answer",
                    self::table(),
                    $poll_id,
                    $question->id
                )
            );

            $result = [
                'question_id'    => (int) $question->id,
                'question_text'  => $question->question_text,
                'za'             => 0,
                'przeciw'        => 0,
                'wstrzymuje_sie' => 0,
                'total'          => 0,
            ];

            foreach ( $counts as $row ) {
                if ( isset( $result[ $row->answer ] ) ) {
                    $result[ $row->answer ] = (int) $row->cnt;
                    $result['total'] += (int) $row->cnt;
                }
            }

            $questions[] = $result;
        }

        return [
            'total_voters' => $total_voters,
            'questions'    => $questions,
        ];
    }

    /**
     * Get anonymous voter list with pseudonyms.
     *
     * @return array<int, array{pseudonym: string, voted_at: string}>
     */
    public static function get_voters_anonymous( int $poll_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT v.user_id, v.voted_at, u.display_name
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
            $user_id  = (int) $row->user_id;
            $nickname = get_user_meta( $user_id, 'nickname', true );
            $location = get_user_meta( $user_id, 'user_registration_miejsce_spotkania', true );
            $gsm      = get_user_meta( $user_id, 'user_registration_GSM', true );

            $voters[] = [
                'pseudonym' => $nickname ?: $row->display_name,
                'location'  => $location ?: '',
                'gsm'       => $gsm ?: '',
                'voted_at'  => $row->voted_at,
            ];
        }

        return $voters;
    }
}
