<?php

namespace Openvote\Tests\Unit;

use OpenvoteTestConfig;

/**
 * Czyste funkcje pomocnicze Openvote_Heartbeat (bez bazy i bez WordPressa):
 * scalanie śledzonych zadań, wykrywanie porzuconych zadań, ostrzeżenie o nieaktywnym cronie,
 * wygaśnięcie ankiety.
 */
class HeartbeatTest extends TestCase {

    // ── merge_tracked ────────────────────────────────────────────────────────

    public function test_merge_applies_own_removal_when_untouched(): void {
        $merged = \Openvote_Heartbeat::merge_tracked(
            [ 'p1' => 'jobA', 'm2' => 'jobB' ],
            [ 'm2' => 'jobB' ],
            [ 'p1' => 'jobA', 'm2' => 'jobB' ]
        );
        $this->assertSame( [ 'm2' => 'jobB' ], $merged );
    }

    public function test_merge_keeps_entry_added_concurrently_by_panel(): void {
        // Panel uruchomił w trakcie ticka nowe zadanie p5 — heartbeat nie może go zgubić.
        $merged = \Openvote_Heartbeat::merge_tracked(
            [ 'p1' => 'jobA' ],
            [],
            [ 'p1' => 'jobA', 'p5' => 'jobNew' ]
        );
        $this->assertSame( [ 'p5' => 'jobNew' ], $merged );
    }

    public function test_merge_does_not_remove_entry_replaced_concurrently(): void {
        // Heartbeat skończył stare jobA, ale panel w międzyczasie podmienił p1 na jobC.
        $merged = \Openvote_Heartbeat::merge_tracked(
            [ 'p1' => 'jobA' ],
            [],
            [ 'p1' => 'jobC' ]
        );
        $this->assertSame( [ 'p1' => 'jobC' ], $merged );
    }

    public function test_merge_applies_own_update_and_addition(): void {
        $merged = \Openvote_Heartbeat::merge_tracked(
            [ 'p1' => '' ],
            [ 'p1' => 'jobR', 'p9' => 'jobAuto' ],
            [ 'p1' => '' ]
        );
        $this->assertSame( [ 'p1' => 'jobR', 'p9' => 'jobAuto' ], $merged );
    }

    // ── is_job_stale ─────────────────────────────────────────────────────────

    public function test_panel_job_with_recent_batch_is_not_stale(): void {
        $now = 1_000_000;
        $job = [ 'owner' => 'panel', 'last_activity' => $now - 60, 'started_at' => $now - 3000 ];
        $this->assertFalse( \Openvote_Heartbeat::is_job_stale( $job, $now ) );
    }

    public function test_panel_job_idle_for_five_minutes_is_stale(): void {
        $now = 1_000_000;
        $job = [ 'owner' => 'panel', 'last_activity' => $now - \Openvote_Heartbeat::STALE_AFTER ];
        $this->assertTrue( \Openvote_Heartbeat::is_job_stale( $job, $now ) );
    }

    public function test_legacy_job_falls_back_to_started_at(): void {
        $now = 1_000_000;
        $this->assertFalse( \Openvote_Heartbeat::is_job_stale( [ 'started_at' => $now - 10 ], $now ) );
        $this->assertTrue( \Openvote_Heartbeat::is_job_stale( [ 'started_at' => $now - 900 ], $now ) );
        $this->assertTrue( \Openvote_Heartbeat::is_job_stale( [], $now ) );
    }

    public function test_heartbeat_owned_job_is_always_takeable(): void {
        $now = 1_000_000;
        $this->assertTrue( \Openvote_Heartbeat::is_job_stale( [ 'owner' => 'heartbeat', 'last_activity' => $now ], $now ) );
    }

    // ── is_tick_stale ────────────────────────────────────────────────────────

    public function test_tick_staleness(): void {
        $now = 1_000_000;
        $this->assertTrue( \Openvote_Heartbeat::is_tick_stale( 0, $now ), 'nigdy nie uruchomiony' );
        $this->assertFalse( \Openvote_Heartbeat::is_tick_stale( $now - 15 * 60, $now ) );
        $this->assertTrue( \Openvote_Heartbeat::is_tick_stale( $now - 20 * 60, $now ) );
    }

    // ── survey_time_expired ──────────────────────────────────────────────────

    public function test_survey_with_date_only_end_expires_after_end_of_day(): void {
        $survey = (object) [ 'status' => 'open', 'date_end' => '2026-03-05' ];

        OpenvoteTestConfig::$mock_time = mktime( 23, 59, 0, 3, 5, 2026 );
        $this->assertFalse( \Openvote_Heartbeat::survey_time_expired( $survey ) );

        OpenvoteTestConfig::$mock_time = mktime( 0, 0, 1, 3, 6, 2026 );
        $this->assertTrue( \Openvote_Heartbeat::survey_time_expired( $survey ) );
    }

    public function test_closed_or_draft_survey_is_never_expired(): void {
        OpenvoteTestConfig::$mock_time = mktime( 12, 0, 0, 3, 6, 2026 );
        $this->assertFalse( \Openvote_Heartbeat::survey_time_expired( (object) [ 'status' => 'closed', 'date_end' => '2026-03-05 10:00:00' ] ) );
        $this->assertFalse( \Openvote_Heartbeat::survey_time_expired( (object) [ 'status' => 'draft', 'date_end' => '2026-03-05 10:00:00' ] ) );
    }
}
