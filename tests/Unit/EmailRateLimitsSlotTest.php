<?php

namespace Openvote\Tests\Unit;

use OpenvoteTestConfig;

/**
 * Tests for Openvote_Email_Rate_Limits::get_current_slots().
 *
 * Time is controlled via OpenvoteTestConfig::$mock_time.
 * openvote_current_time_for_voting() is defined in bootstrap-unit.php
 * and delegates to that config, so these tests need no Brain Monkey function
 * mocks at all.
 */
class EmailRateLimitsSlotTest extends TestCase {

    // ── slot_15 boundary tests ───────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('slotProvider')]
    public function test_slot_15_suffix(int $minute, string $expected_suffix): void {
        // Use 2026-03-05 10:XX:00 as base time.
        OpenvoteTestConfig::$mock_time = mktime(10, $minute, 0, 3, 5, 2026);

        $slots = \Openvote_Email_Rate_Limits::get_current_slots();

        $this->assertStringEndsWith('-' . $expected_suffix, $slots['slot_15']);
    }

    public static function slotProvider(): array {
        return [
            'minute 0  → slot ends -00'  => [0,  '00'],
            'minute 14 → slot ends -00'  => [14, '00'],
            'minute 15 → slot ends -15'  => [15, '15'],
            'minute 29 → slot ends -15'  => [29, '15'],
            'minute 30 → slot ends -30'  => [30, '30'],
            'minute 45 → slot ends -45'  => [45, '45'],
        ];
    }

    // ── slot_hour: date + hour only (no minutes) ─────────────────────────────

    public function test_slot_hour_contains_only_date_and_hour(): void {
        OpenvoteTestConfig::$mock_time = mktime(10, 37, 0, 3, 5, 2026);

        $slots = \Openvote_Email_Rate_Limits::get_current_slots();

        // Format is 'Y-m-d-H' — must not contain the minute portion.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-\d{2}$/', $slots['slot_hour']);
    }

    // ── slot_day: date only ──────────────────────────────────────────────────

    public function test_slot_day_contains_only_date(): void {
        OpenvoteTestConfig::$mock_time = mktime(10, 37, 0, 3, 5, 2026);

        $slots = \Openvote_Email_Rate_Limits::get_current_slots();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $slots['slot_day']);
    }
}
