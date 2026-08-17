<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Services\AttendanceCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    protected AttendanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new AttendanceCalculator;
    }

    #[Test]
    public function session_08_to_15_equals_25200_seconds(): void
    {
        $checkIn = Carbon::parse('2026-01-15 08:00:00');
        $checkOut = Carbon::parse('2026-01-15 15:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(25200, $result);
    }

    #[Test]
    public function two_sessions_sum_to_25200_seconds(): void
    {
        $workDate = Carbon::parse('2026-01-15');

        $session1 = $this->calculator->calculateSessionSeconds(
            Carbon::parse('2026-01-15 08:00:00'),
            Carbon::parse('2026-01-15 12:00:00'),
            $workDate,
        );

        $session2 = $this->calculator->calculateSessionSeconds(
            Carbon::parse('2026-01-15 13:00:00'),
            Carbon::parse('2026-01-15 16:00:00'),
            $workDate,
        );

        $this->assertSame(14400, $session1);
        $this->assertSame(10800, $session2);
        $this->assertSame(25200, $session1 + $session2);
    }

    #[Test]
    public function session_03_to_08_equals_10800_seconds(): void
    {
        $checkIn = Carbon::parse('2026-01-15 03:00:00');
        $checkOut = Carbon::parse('2026-01-15 08:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(10800, $result);
    }

    #[Test]
    public function session_19_to_23_equals_7200_seconds(): void
    {
        $checkIn = Carbon::parse('2026-01-15 19:00:00');
        $checkOut = Carbon::parse('2026-01-15 23:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(7200, $result);
    }

    #[Test]
    public function session_03_to_23_equals_57600_seconds(): void
    {
        $checkIn = Carbon::parse('2026-01-15 03:00:00');
        $checkOut = Carbon::parse('2026-01-15 23:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(57600, $result);
    }

    #[Test]
    public function session_01_to_04_equals_0(): void
    {
        $checkIn = Carbon::parse('2026-01-15 01:00:00');
        $checkOut = Carbon::parse('2026-01-15 04:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function session_22_to_23_equals_0(): void
    {
        $checkIn = Carbon::parse('2026-01-15 22:00:00');
        $checkOut = Carbon::parse('2026-01-15 23:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function session_05_to_21_equals_57600_seconds(): void
    {
        $checkIn = Carbon::parse('2026-01-15 05:00:00');
        $checkOut = Carbon::parse('2026-01-15 21:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate);

        $this->assertSame(57600, $result);
    }

    #[Test]
    public function status_below_required(): void
    {
        $this->assertSame(AttendanceStatus::BelowRequired, $this->calculator->deriveStatus(25199));
    }

    #[Test]
    public function status_completed(): void
    {
        $this->assertSame(AttendanceStatus::Completed, $this->calculator->deriveStatus(25200));
    }

    #[Test]
    public function status_above_required(): void
    {
        $this->assertSame(AttendanceStatus::AboveRequired, $this->calculator->deriveStatus(25201));
    }

    #[Test]
    public function window_boundaries_are_clipped_in_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Muscat']);

        $workDate = Carbon::parse('2026-01-15', 'Asia/Muscat');

        $this->assertSame(60, $this->calculator->calculateSessionSeconds(Carbon::parse('2026-01-15 04:59:00', 'Asia/Muscat'), Carbon::parse('2026-01-15 05:01:00', 'Asia/Muscat'), $workDate));
        $this->assertSame(60, $this->calculator->calculateSessionSeconds(Carbon::parse('2026-01-15 20:59:00', 'Asia/Muscat'), Carbon::parse('2026-01-15 21:01:00', 'Asia/Muscat'), $workDate));
        $this->assertSame(0, $this->calculator->calculateSessionSeconds(Carbon::parse('2026-01-15 04:00:00', 'Asia/Muscat'), Carbon::parse('2026-01-15 04:59:00', 'Asia/Muscat'), $workDate));
        $this->assertSame(0, $this->calculator->calculateSessionSeconds(Carbon::parse('2026-01-15 21:01:00', 'Asia/Muscat'), Carbon::parse('2026-01-15 22:00:00', 'Asia/Muscat'), $workDate));
        $this->assertSame(57600, $this->calculator->calculateSessionSeconds(Carbon::parse('2026-01-15 03:00:00', 'Asia/Muscat'), Carbon::parse('2026-01-15 23:00:00', 'Asia/Muscat'), $workDate));
    }

    #[Test]
    public function remaining_seconds_when_below(): void
    {
        $this->assertSame(1, $this->calculator->calculateRemainingSeconds(25199));
    }

    #[Test]
    public function remaining_seconds_when_at_or_above(): void
    {
        $this->assertSame(0, $this->calculator->calculateRemainingSeconds(25200));
        $this->assertSame(0, $this->calculator->calculateRemainingSeconds(25201));
    }

    #[Test]
    public function extra_seconds_when_above(): void
    {
        $this->assertSame(1, $this->calculator->calculateExtraSeconds(25201));
    }

    #[Test]
    public function extra_seconds_when_at_or_below(): void
    {
        $this->assertSame(0, $this->calculator->calculateExtraSeconds(25200));
        $this->assertSame(0, $this->calculator->calculateExtraSeconds(25199));
    }

    #[Test]
    public function build_daily_summary_closed_sessions(): void
    {
        $summary = $this->calculator->buildDailySummary(
            closedSessionSeconds: [14400, 10800],
            openSessionSeconds: null,
            currentlyWorking: false,
            sessionCount: 2,
        );

        $this->assertSame(25200, $summary['worked_seconds']);
        $this->assertSame(25200, $summary['required_seconds']);
        $this->assertSame(0, $summary['remaining_seconds']);
        $this->assertSame(0, $summary['extra_seconds']);
        $this->assertSame(AttendanceStatus::Completed, $summary['status']);
        $this->assertSame(2, $summary['session_count']);
        $this->assertFalse($summary['currently_working']);
    }

    #[Test]
    public function build_daily_summary_with_open_session(): void
    {
        $summary = $this->calculator->buildDailySummary(
            closedSessionSeconds: [14400],
            openSessionSeconds: 7200,
            currentlyWorking: true,
            sessionCount: 2,
        );

        $this->assertSame(21600, $summary['worked_seconds']);
        $this->assertSame(3600, $summary['remaining_seconds']);
        $this->assertSame(AttendanceStatus::BelowRequired, $summary['status']);
        $this->assertSame(2, $summary['session_count']);
        $this->assertTrue($summary['currently_working']);
    }

    #[Test]
    public function open_session_calculation_respects_window(): void
    {
        $checkIn = Carbon::parse('2026-01-15 03:00:00');
        $now = Carbon::parse('2026-01-15 08:00:00');
        $workDate = Carbon::parse('2026-01-15');

        $result = $this->calculator->calculateOpenSessionSeconds($checkIn, $now, $workDate);

        $this->assertSame(10800, $result);
    }
}
