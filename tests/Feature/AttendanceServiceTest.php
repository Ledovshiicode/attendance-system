<?php

namespace Tests\Feature;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkState;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(AttendanceService::class);

        $this->service = $this->app->make(AttendanceService::class);
    }

    private function createActiveEmployee(): Employee
    {
        $user = User::create([
            'name' => 'Employee',
            'email' => 'employee@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP001',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => true,
        ]);
    }

    private function createInactiveEmployee(): Employee
    {
        $user = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP002',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => false,
        ]);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);
    }

    private function createLeaveRequest(Employee $employee, LeaveRequestStatus $status, Carbon $from, Carbon $to): LeaveRequest
    {
        $leaveType = LeaveType::create([
            'name' => fake()->unique()->word(),
            'deducts_annual_balance' => true,
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'total_days' => 1,
            'reason' => 'Leave',
            'status' => $status,
        ]);
    }

    public function test_employee_can_check_in(): void
    {
        $employee = $this->createActiveEmployee();
        $now = Carbon::parse('2026-01-15 08:00:00');

        $session = $this->service->checkIn($employee, $now);

        $this->assertNotNull($session);
        $this->assertSame($employee->id, $session->employee_id);
        $this->assertSame('2026-01-15', $session->work_date->toDateString());
        $this->assertSame('08:00:00', $session->check_in_at->format('H:i:s'));
        $this->assertNull($session->check_out_at);
        $this->assertSame(0, $session->counted_seconds);
        $this->assertSame(AttendanceSource::Employee, $session->source);
    }

    public function test_attendance_uses_application_local_timezone_for_utc_inputs(): void
    {
        config(['app.timezone' => 'Asia/Muscat']);

        $employee = $this->createActiveEmployee();
        $checkInTime = Carbon::parse('2026-01-14 21:30:00', 'UTC');
        $checkOutTime = Carbon::parse('2026-01-15 02:30:00', 'UTC');

        $this->service->checkIn($employee, $checkInTime);
        $session = $this->service->checkOut($employee, $checkOutTime);

        $this->assertSame('2026-01-15', $session->work_date->toDateString());
        $this->assertSame(5400, $session->counted_seconds);
    }

    public function test_check_in_creates_open_session(): void
    {
        $employee = $this->createActiveEmployee();
        $now = Carbon::parse('2026-01-15 08:00:00');

        $this->service->checkIn($employee, $now);

        $openSession = $this->service->getOpenSession($employee);

        $this->assertNotNull($openSession);
        $this->assertTrue($openSession->isOpen());
    }

    public function test_second_check_in_while_session_open_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();
        $now = Carbon::parse('2026-01-15 08:00:00');

        $this->service->checkIn($employee, $now);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has an open attendance session');

        $this->service->checkIn($employee, $now->addHour());
    }

    public function test_employee_can_check_out(): void
    {
        $employee = $this->createActiveEmployee();
        $checkInTime = Carbon::parse('2026-01-15 08:00:00');
        $checkOutTime = Carbon::parse('2026-01-15 15:00:00');

        $this->service->checkIn($employee, $checkInTime);
        $session = $this->service->checkOut($employee, $checkOutTime);

        $this->assertNotNull($session->check_out_at);
        $this->assertSame(25200, $session->counted_seconds);
        $this->assertFalse($session->isOpen());
    }

    public function test_check_out_stores_counted_seconds_correctly(): void
    {
        $employee = $this->createActiveEmployee();
        $checkInTime = Carbon::parse('2026-01-15 08:00:00');
        $checkOutTime = Carbon::parse('2026-01-15 12:00:00');

        $this->service->checkIn($employee, $checkInTime);
        $session = $this->service->checkOut($employee, $checkOutTime);

        $this->assertSame(14400, $session->counted_seconds);
    }

    public function test_check_out_without_open_session_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No open attendance session found');

        $this->service->checkOut($employee, Carbon::now());
    }

    public function test_check_out_time_before_check_in_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();
        $checkInTime = Carbon::parse('2026-01-15 08:00:00');

        $this->service->checkIn($employee, $checkInTime);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Check-out time must be after check-in time');

        $this->service->checkOut($employee, $checkInTime->subHour());
    }

    public function test_inactive_employee_cannot_check_in(): void
    {
        $employee = $this->createInactiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not active');

        $this->service->checkIn($employee, Carbon::now());
    }

    public function test_multiple_sessions_on_one_day_are_summed(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 08:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 12:00:00'));

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 13:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 16:00:00'));

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'));

        $this->assertSame(25200, $summary['worked_seconds']);
        $this->assertSame(AttendanceStatus::Completed, $summary['attendance_status']);
        $this->assertSame(2, $summary['session_count']);
        $this->assertFalse($summary['currently_working']);
    }

    public function test_open_session_contributes_to_daily_summary(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 08:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 12:00:00'));

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 13:00:00'));

        $summary = $this->service->getDailySummary(
            $employee,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 15:00:00'),
        );

        $this->assertSame(21600, $summary['worked_seconds']);
        $this->assertSame(3600, $summary['remaining_seconds']);
        $this->assertTrue($summary['currently_working']);
        $this->assertSame(2, $summary['session_count']);
    }

    public function test_time_before_05_is_excluded(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 03:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 08:00:00'));

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'));

        $this->assertSame(10800, $summary['worked_seconds']);
    }

    public function test_time_after_21_is_excluded(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 19:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 23:00:00'));

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'));

        $this->assertSame(7200, $summary['worked_seconds']);
    }

    public function test_daily_status_below_completed_above(): void
    {
        $employee = $this->createActiveEmployee();

        // Below
        $this->service->checkIn($employee, Carbon::parse('2026-01-15 08:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 14:59:00'));
        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'));
        $this->assertSame(AttendanceStatus::BelowRequired, $summary['attendance_status']);

        // Completed
        $this->service->checkIn($employee, Carbon::parse('2026-01-16 08:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-16 15:00:00'));
        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-16'));
        $this->assertSame(AttendanceStatus::Completed, $summary['attendance_status']);

        // Above
        $this->service->checkIn($employee, Carbon::parse('2026-01-17 08:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-17 16:00:00'));
        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-17'));
        $this->assertSame(AttendanceStatus::AboveRequired, $summary['attendance_status']);
    }

    public function test_overnight_session_counts_only_work_date_hours(): void
    {
        $employee = $this->createActiveEmployee();

        // Check in at 19:00 on Jan 15, open session
        $this->service->checkIn($employee, Carbon::parse('2026-01-15 19:00:00'));

        // Check summary for Jan 15 at 01:00 on Jan 16 (overnight)
        $summary = $this->service->getDailySummary(
            $employee,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-16 01:00:00'),
        );

        // Window ends at 21:00 on work_date, so max counted = 19:00-21:00 = 7200s
        $this->assertSame(7200, $summary['worked_seconds']);
        $this->assertTrue($summary['currently_working']);
    }

    public function test_check_out_after_midnight_counts_only_work_date_window(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 19:00:00'));
        $session = $this->service->checkOut($employee, Carbon::parse('2026-01-16 01:00:00'));

        // Window end is 21:00 on Jan 15, check-out at 01:00 Jan 16 is beyond window
        // effective_end = min(01:00 Jan 16, 21:00 Jan 15) = 21:00 Jan 15
        $this->assertSame(7200, $session->counted_seconds);
    }

    public function test_work_state_today_before_21_without_attendance_is_not_checked_in_yet(): void
    {
        $employee = $this->createActiveEmployee();

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'), Carbon::parse('2026-01-15 20:59:00'));

        $this->assertSame(WorkState::NotCheckedInYet, $summary['work_state']);
        $this->assertNull($summary['attendance_status']);
    }

    public function test_work_state_today_after_21_without_attendance_is_absent(): void
    {
        $employee = $this->createActiveEmployee();

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'), Carbon::parse('2026-01-15 21:00:00'));

        $this->assertSame(WorkState::Absent, $summary['work_state']);
        $this->assertSame(AttendanceStatus::Absent, $summary['attendance_status']);
    }

    public function test_future_date_is_not_absent(): void
    {
        $employee = $this->createActiveEmployee();

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-16'), Carbon::parse('2026-01-15 21:00:00'));

        $this->assertSame(WorkState::NotCheckedInYet, $summary['work_state']);
        $this->assertNull($summary['attendance_status']);
    }

    public function test_approved_leave_sets_on_leave_but_pending_and_rejected_do_not(): void
    {
        $employee = $this->createActiveEmployee();
        $date = Carbon::parse('2026-01-15');

        $this->createLeaveRequest($employee, LeaveRequestStatus::Pending, $date, $date);
        $this->createLeaveRequest($employee, LeaveRequestStatus::Rejected, $date, $date);

        $summary = $this->service->getDailySummary($employee, $date, Carbon::parse('2026-01-15 21:00:00'));
        $this->assertSame(WorkState::Absent, $summary['work_state']);

        $this->createLeaveRequest($employee, LeaveRequestStatus::Approved, $date, $date);

        $summary = $this->service->getDailySummary($employee, $date, Carbon::parse('2026-01-15 21:00:00'));
        $this->assertSame(WorkState::OnLeave, $summary['work_state']);
        $this->assertSame(AttendanceStatus::OnLeave, $summary['attendance_status']);
    }

    public function test_session_entirely_outside_window_does_not_count_as_attendance_presence(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 03:00:00'));
        $this->service->checkOut($employee, Carbon::parse('2026-01-15 04:00:00'));

        $summary = $this->service->getDailySummary($employee, Carbon::parse('2026-01-15'), Carbon::parse('2026-01-15 21:00:00'));

        $this->assertSame(0, $summary['worked_seconds']);
        $this->assertSame(WorkState::Absent, $summary['work_state']);
        $this->assertSame(AttendanceStatus::Absent, $summary['attendance_status']);
    }

    public function test_admin_can_record_manual_attendance_with_audit_fields(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $session = $this->service->recordManualSession(
            $employee,
            Carbon::parse('2026-01-15 04:30:00'),
            Carbon::parse('2026-01-15 06:00:00'),
            $admin,
            'Employee forgot to check in.',
        );

        $this->assertSame(3600, $session->counted_seconds);
        $this->assertSame(AttendanceSource::AdminManual, $session->source);
        $this->assertSame($admin->id, $session->created_by);
        $this->assertSame('Employee forgot to check in.', $session->note);
    }

    public function test_employee_cannot_record_manual_attendance(): void
    {
        $employee = $this->createActiveEmployee();

        $this->expectException(\DomainException::class);

        $this->service->recordManualSession(
            $employee,
            Carbon::parse('2026-01-15 08:00:00'),
            Carbon::parse('2026-01-15 09:00:00'),
            $employee->user,
            'No permission.',
        );
    }

    public function test_manual_attendance_rejects_overlaps_and_allows_touching_endpoints(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 08:00:00'), Carbon::parse('2026-01-15 10:00:00'), $admin, 'First session.');

        try {
            $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 09:59:00'), Carbon::parse('2026-01-15 12:00:00'), $admin, 'Overlap.');
            $this->fail('Expected overlap exception.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('overlaps an existing session', $exception->getMessage());
        }

        $session = $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 10:00:00'), Carbon::parse('2026-01-15 12:00:00'), $admin, 'Adjacent session.');

        $this->assertSame(7200, $session->counted_seconds);
        $this->assertSame(2, AttendanceSession::count());
    }

    public function test_manual_attendance_rejects_overlap_with_open_session(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $this->service->checkIn($employee, Carbon::parse('2026-01-15 08:00:00'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('overlaps an existing session');

        $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 09:00:00'), Carbon::parse('2026-01-15 10:00:00'), $admin, 'Overlap.');
    }

    public function test_manual_attendance_rejects_invalid_checkout_and_missing_reason(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        try {
            $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 10:00:00'), Carbon::parse('2026-01-15 09:00:00'), $admin, 'Bad time.');
            $this->fail('Expected invalid checkout exception.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Check-out time must be after check-in time', $exception->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Please provide a reason');

        $this->service->recordManualSession($employee, Carbon::parse('2026-01-15 08:00:00'), Carbon::parse('2026-01-15 09:00:00'), $admin, '');
    }
}
