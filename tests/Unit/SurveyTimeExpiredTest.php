<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey\Functions;
use OpenvoteTestConfig;

/**
 * Tests for Openvote_Survey::is_time_expired(), ::is_active(), ::is_ended() and ::status_label().
 *
 * Clock is mocked via OpenvoteTestConfig::$mock_time (openvote_current_time_for_voting() stub).
 */
class SurveyTimeExpiredTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OpenvoteTestConfig::$mock_time = strtotime('2026-11-17 12:00:00');
        Functions\when('__')->returnArg(1);
    }

    private function survey(string $status, string $start, string $end): object {
        return (object) ['status' => $status, 'date_start' => $start, 'date_end' => $end];
    }

    public function test_open_survey_with_past_end_is_expired(): void {
        $s = $this->survey('open', '2026-11-10 00:00:00', '2026-11-17 11:59:59');
        $this->assertTrue(\Openvote_Survey::is_time_expired($s));
        $this->assertFalse(\Openvote_Survey::is_active($s));
        $this->assertTrue(\Openvote_Survey::is_ended($s));
    }

    public function test_open_survey_with_future_end_is_not_expired(): void {
        $s = $this->survey('open', '2026-11-10 00:00:00', '2026-11-17 12:00:01');
        $this->assertFalse(\Openvote_Survey::is_time_expired($s));
        $this->assertTrue(\Openvote_Survey::is_active($s));
        $this->assertFalse(\Openvote_Survey::is_ended($s));
    }

    public function test_end_equal_to_now_is_not_expired(): void {
        $s = $this->survey('open', '2026-11-10 00:00:00', '2026-11-17 12:00:00');
        $this->assertFalse(\Openvote_Survey::is_time_expired($s));
        $this->assertTrue(\Openvote_Survey::is_active($s));
    }

    public function test_ten_char_end_date_means_end_of_day(): void {
        $s = $this->survey('open', '2026-11-10', '2026-11-17');
        $this->assertFalse(\Openvote_Survey::is_time_expired($s));
        $this->assertTrue(\Openvote_Survey::is_active($s));

        $s = $this->survey('open', '2026-11-10', '2026-11-16');
        $this->assertTrue(\Openvote_Survey::is_time_expired($s));
        $this->assertFalse(\Openvote_Survey::is_active($s));
    }

    public function test_not_started_survey_is_not_active_nor_expired(): void {
        $s = $this->survey('open', '2026-11-18', '2026-11-20');
        $this->assertFalse(\Openvote_Survey::is_time_expired($s));
        $this->assertFalse(\Openvote_Survey::is_active($s));
    }

    public function test_non_open_statuses_are_never_time_expired(): void {
        $this->assertFalse(\Openvote_Survey::is_time_expired($this->survey('closed', '2026-11-01', '2026-11-02')));
        $this->assertFalse(\Openvote_Survey::is_time_expired($this->survey('draft', '2026-11-01', '2026-11-02')));
        $this->assertTrue(\Openvote_Survey::is_ended($this->survey('closed', '2026-11-01', '2026-12-31')));
        $this->assertFalse(\Openvote_Survey::is_ended($this->survey('draft', '2026-11-01', '2026-11-02')));
    }

    public function test_empty_end_date_is_not_expired_and_not_active(): void {
        $s = $this->survey('open', '2026-11-01 00:00:00', '');
        $this->assertFalse(\Openvote_Survey::is_time_expired($s));
        $this->assertFalse(\Openvote_Survey::is_active($s));
    }

    public function test_status_label_marks_expired_open_survey_as_ended(): void {
        $expired = $this->survey('open', '2026-11-01', '2026-11-16 23:00:00');
        $running = $this->survey('open', '2026-11-01', '2026-11-30 23:00:00');
        $this->assertSame('Zakończona (czas minął)', \Openvote_Survey::status_label('open', $expired));
        $this->assertSame('Trwa', \Openvote_Survey::status_label('open', $running));
        $this->assertSame('Trwa', \Openvote_Survey::status_label('open'));
        $this->assertSame('Zakończona', \Openvote_Survey::status_label('closed', $expired));
    }

    public function test_effective_status_where_uses_voting_clock(): void {
        [$sql, $params] = \Openvote_Survey::effective_status_where('closed');
        $this->assertSame("(status = 'closed' OR (status = 'open' AND date_end < %s))", $sql);
        $this->assertSame(['2026-11-17 12:00:00'], $params);

        [$sql, $params] = \Openvote_Survey::effective_status_where('open');
        $this->assertSame("(status = 'open' AND date_end >= %s)", $sql);
        $this->assertSame(['2026-11-17 12:00:00'], $params);

        [$sql, $params] = \Openvote_Survey::effective_status_where('draft');
        $this->assertSame('status = %s', $sql);
        $this->assertSame(['draft'], $params);
    }
}
