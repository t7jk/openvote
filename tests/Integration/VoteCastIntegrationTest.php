<?php

/**
 * Integration tests for Openvote_Vote::cast() against a real database.
 *
 * Prerequisites:
 *   bash bin/install-wp-tests.sh openvote_test root password localhost latest
 *   WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
 *
 * Each test runs inside the WP_UnitTestCase transaction wrapper so database
 * changes are rolled back after every test.
 */
class VoteCastIntegrationTest extends WP_UnitTestCase {

    /** @var int Poll ID created in setUpBeforeClass */
    private static int $poll_id;

    /** @var int[] Question IDs [question_id_1, question_id_2] */
    private static array $question_ids = [];

    /** @var int[] Valid answer IDs keyed by question_id */
    private static array $valid_answer_ids = [];

    /** @var int User ID for voting */
    private int $user_id;

    // ── Schema setup (once per class) ────────────────────────────────────────

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        Openvote_Activator::activate();
    }

    // ── Per-test fixture ──────────────────────────────────────────────────────

    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        // Create a voter.
        $this->user_id = self::factory()->user->create([
            'user_login' => 'test_voter_' . wp_generate_password(6, false),
            'user_email' => 'voter_' . wp_generate_password(6, false) . '@example.com',
        ]);

        // Insert poll with status='open' and dates spanning test time.
        $polls_table = $wpdb->prefix . 'openvote_polls';
        $wpdb->insert($polls_table, [
            'title'         => 'Test Poll',
            'description'   => '',
            'status'        => 'open',
            'join_mode'     => 'open',
            'vote_mode'     => 'public',
            'target_groups' => null,
            'notify_start'  => 0,
            'notify_end'    => 0,
            'date_start'    => '2020-01-01 00:00:00',
            'date_end'      => '2099-12-31 23:59:59',
            'created_by'    => 1,
            'created_at'    => current_time('mysql'),
        ]);
        self::$poll_id = (int) $wpdb->insert_id;

        // Insert 2 questions, each with 2 answers.
        $questions_table = $wpdb->prefix . 'openvote_questions';
        $answers_table   = $wpdb->prefix . 'openvote_answers';

        self::$question_ids      = [];
        self::$valid_answer_ids  = [];

        for ($q = 1; $q <= 2; $q++) {
            $wpdb->insert($questions_table, [
                'poll_id'    => self::$poll_id,
                'body'       => "Question $q",
                'sort_order' => $q - 1,
            ]);
            $qid = (int) $wpdb->insert_id;
            self::$question_ids[] = $qid;

            // Two answers: first is a regular answer, second is abstain.
            $wpdb->insert($answers_table, [
                'question_id' => $qid,
                'body'        => "Answer A for Q$q",
                'is_abstain'  => 0,
                'sort_order'  => 0,
            ]);
            self::$valid_answer_ids[$qid] = (int) $wpdb->insert_id;

            $wpdb->insert($answers_table, [
                'question_id' => $qid,
                'body'        => "Wstrzymuję się",
                'is_abstain'  => 1,
                'sort_order'  => 1,
            ]);
        }
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_cast_inserts_all_vote_rows(): void {
        global $wpdb;

        $answers = [];
        foreach (self::$question_ids as $qid) {
            $answers[$qid] = self::$valid_answer_ids[$qid];
        }

        $result = Openvote_Vote::cast(self::$poll_id, $this->user_id, $answers);

        $this->assertTrue($result, 'cast() should return true on success');

        $votes_table = $wpdb->prefix . 'openvote_votes';
        $count       = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$votes_table} WHERE poll_id = %d AND user_id = %d",
                self::$poll_id,
                $this->user_id
            )
        );

        $this->assertSame(
            count(self::$question_ids),
            $count,
            'One vote row should be inserted per question'
        );
    }

    public function test_has_voted_returns_true_after_cast(): void {
        $answers = [];
        foreach (self::$question_ids as $qid) {
            $answers[$qid] = self::$valid_answer_ids[$qid];
        }

        Openvote_Vote::cast(self::$poll_id, $this->user_id, $answers);

        $this->assertTrue(
            Openvote_Vote::has_voted(self::$poll_id, $this->user_id),
            'has_voted() must return true after a successful cast()'
        );
    }

    public function test_second_cast_returns_already_voted_error(): void {
        $answers = [];
        foreach (self::$question_ids as $qid) {
            $answers[$qid] = self::$valid_answer_ids[$qid];
        }

        // First vote.
        Openvote_Vote::cast(self::$poll_id, $this->user_id, $answers);

        // Second attempt — cast() detects has_voted() = true before the transaction.
        $result = Openvote_Vote::cast(self::$poll_id, $this->user_id, $answers);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('already_voted', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertSame(403, $data['status'] ?? null);
    }

    public function test_cast_with_invalid_answer_returns_error(): void {
        global $wpdb;

        // Use an answer_id that does not belong to any question in our poll.
        $bogus_answer_id = 999999;

        $qid     = self::$question_ids[0];
        $answers = [$qid => $bogus_answer_id];

        $result = Openvote_Vote::cast(self::$poll_id, $this->user_id, $answers);

        $this->assertInstanceOf(
            WP_Error::class,
            $result,
            'cast() should return WP_Error for an answer_id not belonging to the question'
        );
        $this->assertSame('invalid_answer', $result->get_error_code());
    }
}
