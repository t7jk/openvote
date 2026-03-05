<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Unit tests for Openvote_Vote::cast() — transaction handling.
 *
 * Strategy:
 *  - $GLOBALS['wpdb'] is replaced with a Mockery mock that controls all DB
 *    calls (including those made by Openvote_Poll::get() and has_voted()).
 *  - Openvote_Eligibility is a stub (bootstrap) that always returns true.
 *  - openvote_current_time_for_voting() is defined in bootstrap; the mock
 *    poll uses dates far in the past/future so is_active() always returns true.
 *
 * These tests cover the three outcomes after answer-validation passes:
 *   1. All inserts succeed → COMMIT, return true.
 *   2. An insert fails (non-duplicate) → ROLLBACK, WP_Error('vote_failed').
 *   3. An insert fails with "Duplicate" → ROLLBACK, WP_Error('already_voted').
 */
class VoteCastTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Stub WP functions used inside cast() and its callees.
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('current_time')->justReturn('2026-01-01 00:00:00');
        Functions\when('__')->returnArg(1);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Build a $wpdb mock that satisfies all DB calls made by cast() for a
     * poll with one question (id=1) and one answer (id=1).
     *
     * get_var() returns in sequence:
     *   has_voted check → '0'   (not yet voted)
     *   answer validation → '1' (answer valid)
     *
     * insert() is configured by the caller via $insertReturn.
     *
     * @param int|false $insertReturn  Return value of insert() (1 = success, false = failure).
     * @param string    $lastError     Value of $wpdb->last_error after insert() failure.
     * @return \Mockery\MockInterface
     */
    private function makeWpdb(int|false $insertReturn, string $lastError = ''): \Mockery\MockInterface {
        $question = (object)['id' => 1, 'poll_id' => 1];
        $answer   = (object)['id' => 1, 'question_id' => 1];

        // A poll that is always active (dates span year 2020-2099, status=open).
        $poll = (object)[
            'id'            => 1,
            'status'        => 'open',
            'date_start'    => '2020-01-01 00:00:00',
            'date_end'      => '2099-12-31 23:59:59',
            'target_groups' => null,
            'join_mode'     => 'open',
            'vote_mode'     => 'public',
        ];

        $wpdb             = Mockery::mock();
        $wpdb->prefix     = 'wp_';
        $wpdb->last_error = $lastError;

        // prepare() just returns the format string unchanged; actual values
        // are not important for these tests.
        $wpdb->shouldReceive('prepare')->andReturnArg(0);

        // Poll::get() ─────────────────────────────────────────────────────────
        // get_row() → poll, then two get_results() → questions + answers.
        $wpdb->shouldReceive('get_row')->once()->andReturn($poll);
        $wpdb->shouldReceive('get_results')
             ->twice()
             ->andReturn([$question], [$answer]);

        // has_voted() then answer validation.
        $wpdb->shouldReceive('get_var')
             ->twice()
             ->andReturn('0', '1');  // not voted, answer valid

        // Transaction.
        $wpdb->shouldReceive('query')->with('START TRANSACTION')->once();

        $wpdb->shouldReceive('insert')->once()->andReturn($insertReturn);

        if ($insertReturn !== false) {
            $wpdb->shouldReceive('query')->with('COMMIT')->once();
        } else {
            $wpdb->shouldReceive('query')->with('ROLLBACK')->once();
        }

        return $wpdb;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Test cases
    // ────────────────────────────────────────────────────────────────────────

    public function test_successful_vote_commits_and_returns_true(): void {
        $GLOBALS['wpdb'] = $this->makeWpdb(1);

        $result = \Openvote_Vote::cast(1, 1, [1 => 1]);

        $this->assertTrue($result);
    }

    public function test_insert_failure_rolls_back_and_returns_vote_failed_error(): void {
        $GLOBALS['wpdb'] = $this->makeWpdb(false, '');  // last_error = '' → not a duplicate

        $result = \Openvote_Vote::cast(1, 1, [1 => 1]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('vote_failed', $result->get_error_code());
        $this->assertSame(['status' => 500], $result->get_error_data());
    }

    public function test_duplicate_insert_rolls_back_and_returns_already_voted_error(): void {
        $GLOBALS['wpdb'] = $this->makeWpdb(false, 'Duplicate entry for key unique_vote');

        $result = \Openvote_Vote::cast(1, 1, [1 => 1]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('already_voted', $result->get_error_code());
        $this->assertSame(['status' => 403], $result->get_error_data());
    }
}
