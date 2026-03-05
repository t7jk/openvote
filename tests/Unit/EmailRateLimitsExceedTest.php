<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey\Functions;
use OpenvoteTestConfig;

/**
 * Tests for Openvote_Email_Rate_Limits::would_exceed_limits().
 *
 * Limits are configured via OpenvoteTestConfig.
 * get_option() is mocked with Brain Monkey so get_counts() returns
 * the values we specify without hitting a real database.
 */
class EmailRateLimitsExceedTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // would_exceed_limits() calls __() to build the error message string.
        Functions\when('__')->returnArg(1);
    }

    /**
     * Configure Brain Monkey's get_option() to return specific stored counts.
     *
     * Slots are derived dynamically from get_current_slots() so the comparison
     * inside get_counts() (stored_slot === current_slot) always passes.
     *
     * @param int $count_15   count for 15-min window
     * @param int $count_hour count for hour window
     * @param int $count_day  count for day window
     */
    private function stubCounts(int $count_15, int $count_hour, int $count_day): void {
        $slots = \Openvote_Email_Rate_Limits::get_current_slots();

        $map = [
            'openvote_email_sent_15min_slot'  => $slots['slot_15'],
            'openvote_email_sent_15min_count' => (string) $count_15,
            'openvote_email_sent_hour_slot'   => $slots['slot_hour'],
            'openvote_email_sent_hour_count'  => (string) $count_hour,
            'openvote_email_sent_day_slot'    => $slots['slot_day'],
            'openvote_email_sent_day_count'   => (string) $count_day,
        ];

        Functions\when('get_option')->alias(
            static function(string $name, mixed $default = null) use ($map): mixed {
                return $map[$name] ?? $default;
            }
        );
    }

    // ── All limits disabled (0) ──────────────────────────────────────────────

    public function test_all_limits_zero_never_exceed(): void {
        // OpenvoteTestConfig defaults to 0 for all limits.
        $this->stubCounts(99999, 99999, 99999);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(1000);

        $this->assertFalse($result['exceeded']);
        $this->assertSame('', $result['limit_type']);
    }

    // ── 15-min limit ─────────────────────────────────────────────────────────

    public function test_15min_limit_not_exceeded_at_exact_boundary(): void {
        // count_15=95 + n=5 = 100 = limit → NOT exceeded (strictly greater required)
        OpenvoteTestConfig::$limit_15min = 100;
        $this->stubCounts(95, 0, 0);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(5);

        $this->assertFalse($result['exceeded']);
    }

    public function test_15min_limit_exceeded_by_one(): void {
        // count_15=95 + n=6 = 101 > 100 → exceeded
        OpenvoteTestConfig::$limit_15min = 100;
        $this->stubCounts(95, 0, 0);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(6);

        $this->assertTrue($result['exceeded']);
        $this->assertSame('15min', $result['limit_type']);
        $this->assertSame(100, $result['limit_max']);
    }

    // ── Hour limit ───────────────────────────────────────────────────────────

    public function test_hour_limit_exceeded(): void {
        // count_hour=490 + n=15 = 505 > 500 → exceeded
        OpenvoteTestConfig::$limit_15min = 0;   // 15-min disabled
        OpenvoteTestConfig::$limit_hour  = 500;
        $this->stubCounts(0, 490, 0);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(15);

        $this->assertTrue($result['exceeded']);
        $this->assertSame('hour', $result['limit_type']);
        $this->assertSame(500, $result['limit_max']);
    }

    // ── Day limit ────────────────────────────────────────────────────────────

    public function test_day_limit_exceeded(): void {
        // count_day=1990 + n=20 = 2010 > 2000 → exceeded
        OpenvoteTestConfig::$limit_15min = 0;
        OpenvoteTestConfig::$limit_hour  = 0;
        OpenvoteTestConfig::$limit_day   = 2000;
        $this->stubCounts(0, 0, 1990);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(20);

        $this->assertTrue($result['exceeded']);
        $this->assertSame('day', $result['limit_type']);
        $this->assertSame(2000, $result['limit_max']);
    }

    // ── Priority: 15min checked before hour, hour before day ────────────────

    public function test_15min_takes_priority_over_hour_and_day(): void {
        OpenvoteTestConfig::$limit_15min = 10;
        OpenvoteTestConfig::$limit_hour  = 500;
        OpenvoteTestConfig::$limit_day   = 2000;
        // All three would exceed their limits.
        $this->stubCounts(9, 499, 1999);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(2);

        $this->assertTrue($result['exceeded']);
        $this->assertSame('15min', $result['limit_type'], '15min limit should be reported first');
    }

    public function test_hour_takes_priority_over_day(): void {
        OpenvoteTestConfig::$limit_15min = 0;   // disabled
        OpenvoteTestConfig::$limit_hour  = 500;
        OpenvoteTestConfig::$limit_day   = 2000;
        $this->stubCounts(0, 499, 1999);

        $result = \Openvote_Email_Rate_Limits::would_exceed_limits(2);

        $this->assertTrue($result['exceeded']);
        $this->assertSame('hour', $result['limit_type'], 'hour limit should be reported before day');
    }
}
