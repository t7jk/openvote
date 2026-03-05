<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use OpenvoteTestConfig;

/**
 * Tests for Openvote_Email_Rate_Limits::increment().
 *
 * Uses a Mockery mock for $wpdb (assigned to $GLOBALS['wpdb']) and
 * Brain Monkey for wp_cache_delete().
 *
 * Slot values are computed dynamically from get_current_slots() so they
 * always match what increment() computes internally for the mock time.
 */
class EmailRateLimitsIncrementTest extends TestCase {

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a $wpdb mock whose prepare() substitutes %d/%s/%i placeholders
     * with actual values so query() receives human-readable SQL for assertions.
     *
     * @param list<mixed> $getvarReturns Values to return on successive get_var() calls.
     * @param array       &$queriesLog   Collects every string passed to query().
     * @return \Mockery\MockInterface
     */
    private function makeWpdb(array $getvarReturns, array &$queriesLog): \Mockery\MockInterface {
        $wpdb          = Mockery::mock();
        $wpdb->prefix  = 'wp_';
        $wpdb->options = 'wp_options';

        $wpdb->shouldReceive('prepare')
             ->andReturnUsing(static function(string $tpl, ...$args): string {
                 $i      = 0;
                 $result = preg_replace_callback(
                     '/%[dsi]/',
                     static function() use (&$i, $args): string {
                         return isset($args[$i]) ? (string) $args[$i++] : '';
                     },
                     $tpl
                 );
                 return $result ?? $tpl;
             });

        $callIdx = 0;
        $wpdb->shouldReceive('get_var')
             ->andReturnUsing(static function() use (&$callIdx, $getvarReturns): mixed {
                 return $getvarReturns[$callIdx++] ?? null;
             });

        $wpdb->shouldReceive('query')
             ->andReturnUsing(static function(string $q) use (&$queriesLog): bool {
                 $queriesLog[] = $q;
                 return true;
             });

        return $wpdb;
    }

    /**
     * Compute the actual slot values for the configured mock_time.
     * Must be called after OpenvoteTestConfig::$mock_time is set.
     */
    private function currentSlots(): array {
        return \Openvote_Email_Rate_Limits::get_current_slots();
    }

    // ── n = 0 → no SQL at all ────────────────────────────────────────────────

    public function test_zero_n_executes_no_sql(): void {
        // Use a fresh mock without get_var/query setup.
        $wpdb          = Mockery::mock();
        $wpdb->prefix  = 'wp_';
        $wpdb->options = 'wp_options';
        $wpdb->shouldNotReceive('query');
        $wpdb->shouldNotReceive('get_var');

        Functions\expect('wp_cache_delete')->never();

        $GLOBALS['wpdb'] = $wpdb;
        \Openvote_Email_Rate_Limits::increment(0);

        // Verification is done by Mockery (shouldNotReceive).
        $this->expectNotToPerformAssertions();
    }

    // ── n = 5, same slot → START + 6 SELECT + 6 UPDATE + COMMIT + 6 cache deletes

    public function test_same_slot_executes_correct_sql_sequence(): void {
        $slots        = $this->currentSlots();
        $storedCount  = 10;
        $getvarValues = [
            $slots['slot_15'],   (string) $storedCount,   // slot_15 pair
            $slots['slot_hour'], (string) $storedCount,   // slot_hour pair
            $slots['slot_day'],  (string) $storedCount,   // slot_day pair
        ];

        $queries         = [];
        $GLOBALS['wpdb'] = $this->makeWpdb($getvarValues, $queries);

        Functions\expect('wp_cache_delete')->times(6);

        \Openvote_Email_Rate_Limits::increment(5);

        // Total query() calls: 1 START + 6 UPDATE + 1 COMMIT = 8
        $this->assertCount(8, $queries, 'Expected 8 query() calls');
        $this->assertSame('START TRANSACTION', $queries[0]);
        $this->assertSame('COMMIT', end($queries));

        // same slot → new_count = storedCount + n = 15 for every window.
        $countUpdates = array_values(array_filter($queries, static function(string $q): bool {
            return str_contains($q, 'option_value') && str_contains($q, '_count');
        }));

        $this->assertCount(3, $countUpdates, 'Expected 3 count UPDATE queries');
        foreach ($countUpdates as $q) {
            $this->assertStringContainsString(
                'option_value = 15',
                $q,
                'Same slot: new_count must be stored_count + n'
            );
        }
    }

    // ── n = 5, new slot → UPDATE count with value = n (not stored_count + n) ─

    public function test_new_slot_resets_count_to_n(): void {
        $storedCount  = 10;
        $getvarValues = [
            'OLD_SLOT_15',   (string) $storedCount,   // stored_slot ≠ current_slot
            'OLD_SLOT_HOUR', (string) $storedCount,
            'OLD_SLOT_DAY',  (string) $storedCount,
        ];

        $queries         = [];
        $GLOBALS['wpdb'] = $this->makeWpdb($getvarValues, $queries);

        Functions\when('wp_cache_delete')->justReturn(true);

        \Openvote_Email_Rate_Limits::increment(5);

        // new slot → new_count = n = 5 (not 10+5=15).
        $countUpdates = array_values(array_filter($queries, static function(string $q): bool {
            return str_contains($q, 'option_value') && str_contains($q, '_count');
        }));

        $this->assertCount(3, $countUpdates);
        foreach ($countUpdates as $q) {
            $this->assertStringContainsString(
                'option_value = 5',
                $q,
                'New slot: count must be reset to n, not accumulated'
            );
        }
    }

    // ── Ordering: TRANSACTION first, COMMIT last ──────────────────────────────

    public function test_transaction_wraps_all_updates(): void {
        $slots        = $this->currentSlots();
        $getvarValues = [
            $slots['slot_15'],   '0',
            $slots['slot_hour'], '0',
            $slots['slot_day'],  '0',
        ];

        $queries         = [];
        $GLOBALS['wpdb'] = $this->makeWpdb($getvarValues, $queries);

        Functions\when('wp_cache_delete')->justReturn(true);

        \Openvote_Email_Rate_Limits::increment(3);

        $this->assertSame('START TRANSACTION', $queries[0],   'START TRANSACTION must be first');
        $this->assertSame('COMMIT',            end($queries), 'COMMIT must be last');

        // All UPDATE queries must be between START and COMMIT.
        $updateIndices = array_keys(array_filter($queries, static fn($q) => str_starts_with(trim($q), 'UPDATE')));
        $this->assertCount(6, $updateIndices);
        foreach ($updateIndices as $idx) {
            $this->assertGreaterThan(0,                     $idx, 'UPDATE must come after START');
            $this->assertLessThan(count($queries) - 1,      $idx, 'UPDATE must come before COMMIT');
        }
    }
}
