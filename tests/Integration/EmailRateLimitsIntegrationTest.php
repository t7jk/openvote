<?php

/**
 * Integration tests for Openvote_Email_Rate_Limits::increment() and
 * get_counts() against a real database.
 *
 * Design note:
 *   increment() runs its own START TRANSACTION … COMMIT.  In MySQL, issuing
 *   START TRANSACTION inside an already-open transaction implicitly commits
 *   the outer transaction — which would destroy WP_UnitTestCase's rollback
 *   isolation.  To avoid this, we override start_transaction() as a no-op
 *   and clean up explicitly in tearDown().
 *
 * Prerequisites:
 *   bash bin/install-wp-tests.sh openvote_test root password localhost latest
 *   WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
 */
class EmailRateLimitsIntegrationTest extends WP_UnitTestCase {

    private const OPT_NAMES = [
        'openvote_email_sent_15min_slot',
        'openvote_email_sent_15min_count',
        'openvote_email_sent_hour_slot',
        'openvote_email_sent_hour_count',
        'openvote_email_sent_day_slot',
        'openvote_email_sent_day_count',
    ];

    // ── Disable WP's transaction wrapper ─────────────────────────────────────
    // This lets increment() manage its own START TRANSACTION / COMMIT without
    // nesting issues.  tearDown() still issues a ROLLBACK which is a no-op
    // when no transaction is open, so no harm done.

    public function start_transaction(): void {
        // intentionally empty — see class-level comment.
    }

    // ── Setup / teardown ──────────────────────────────────────────────────────

    public function setUp(): void {
        parent::setUp();
        // Ensure all six option rows exist so UPDATE queries in increment()
        // find rows to update.
        foreach (self::OPT_NAMES as $name) {
            update_option($name, '');
        }
    }

    public function tearDown(): void {
        // Remove options so tests don't bleed into each other.
        foreach (self::OPT_NAMES as $name) {
            delete_option($name);
        }
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Seed all six options with the given slot values and counts.
     * Useful for testing "same slot" accumulation or "reset on new slot".
     */
    private function seedOptions(
        string $slot_15,
        int    $count_15,
        string $slot_hour,
        int    $count_hour,
        string $slot_day,
        int    $count_day
    ): void {
        update_option('openvote_email_sent_15min_slot',  $slot_15);
        update_option('openvote_email_sent_15min_count', (string) $count_15);
        update_option('openvote_email_sent_hour_slot',   $slot_hour);
        update_option('openvote_email_sent_hour_count',  (string) $count_hour);
        update_option('openvote_email_sent_day_slot',    $slot_day);
        update_option('openvote_email_sent_day_count',   (string) $count_day);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * increment(5) with no matching prior slot (blank slot stored in setUp)
     * should write 5 as the new count for every window.
     */
    public function test_increment_updates_options(): void {
        // Options seeded with '' → stored_slot ≠ current_slot → new_count = 5.
        Openvote_Email_Rate_Limits::increment(5);

        $count_15   = (int) get_option('openvote_email_sent_15min_count', 0);
        $count_hour = (int) get_option('openvote_email_sent_hour_count',  0);
        $count_day  = (int) get_option('openvote_email_sent_day_count',   0);

        $this->assertSame(5, $count_15,   '15-min count should be 5');
        $this->assertSame(5, $count_hour, 'Hour count should be 5');
        $this->assertSame(5, $count_day,  'Day count should be 5');
    }

    /**
     * Two calls of increment(3) within the same slot should give count = 6.
     */
    public function test_increment_accumulates_in_same_slot(): void {
        $slots = Openvote_Email_Rate_Limits::get_current_slots();
        // Seed with current slot values so the second call accumulates.
        $this->seedOptions(
            $slots['slot_15'],   0,
            $slots['slot_hour'], 0,
            $slots['slot_day'],  0
        );

        Openvote_Email_Rate_Limits::increment(3);
        Openvote_Email_Rate_Limits::increment(3);

        $count_15   = (int) get_option('openvote_email_sent_15min_count', 0);
        $count_hour = (int) get_option('openvote_email_sent_hour_count',  0);
        $count_day  = (int) get_option('openvote_email_sent_day_count',   0);

        $this->assertSame(6, $count_15,   '15-min count should accumulate to 6');
        $this->assertSame(6, $count_hour, 'Hour count should accumulate to 6');
        $this->assertSame(6, $count_day,  'Day count should accumulate to 6');
    }

    /**
     * When the stored slot is outdated, increment() should reset the count
     * to $n rather than adding to the old count.
     */
    public function test_increment_resets_on_new_slot(): void {
        // Seed with obviously stale slot values.
        $this->seedOptions(
            'OLD_SLOT_15',   100,
            'OLD_SLOT_HOUR', 100,
            'OLD_SLOT_DAY',  100
        );

        Openvote_Email_Rate_Limits::increment(4);

        $count_15   = (int) get_option('openvote_email_sent_15min_count', 0);
        $count_hour = (int) get_option('openvote_email_sent_hour_count',  0);
        $count_day  = (int) get_option('openvote_email_sent_day_count',   0);

        $this->assertSame(4, $count_15,   '15-min count should reset to 4, not 104');
        $this->assertSame(4, $count_hour, 'Hour count should reset to 4, not 104');
        $this->assertSame(4, $count_day,  'Day count should reset to 4, not 104');
    }

    /**
     * increment(0) must be a no-op — option values must remain unchanged.
     */
    public function test_increment_zero_is_noop(): void {
        $slots = Openvote_Email_Rate_Limits::get_current_slots();
        $this->seedOptions(
            $slots['slot_15'],   10,
            $slots['slot_hour'], 10,
            $slots['slot_day'],  10
        );

        Openvote_Email_Rate_Limits::increment(0);

        $count_15   = (int) get_option('openvote_email_sent_15min_count', 0);
        $count_hour = (int) get_option('openvote_email_sent_hour_count',  0);
        $count_day  = (int) get_option('openvote_email_sent_day_count',   0);

        $this->assertSame(10, $count_15,   'Count should remain 10 after increment(0)');
        $this->assertSame(10, $count_hour, 'Count should remain 10 after increment(0)');
        $this->assertSame(10, $count_day,  'Count should remain 10 after increment(0)');
    }
}
