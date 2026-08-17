<?php

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $employeeUser;

    protected LeaveType $annualType;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));

        $this->annualType = LeaveType::create([
            'name' => 'Annual',
            'deducts_annual_balance' => true,
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'employee@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_statistics_are_correct(): void
    {
        $completed = $this->employee('Completed Employee');
        $below = $this->employee('Below Employee');
        $above = $this->employee('Above Employee');
        $open = $this->employee('Open Employee');
        $absent = $this->employee('Absent Employee');
        $onLeave = $this->employee('On Leave Employee');
        $pendingLeave = $this->employee('Pending Leave Employee');
        $rejectedLeave = $this->employee('Rejected Leave Employee');
        $inactive = $this->employee('Inactive Employee', false);

        $service = app(AttendanceService::class);
        $today = Carbon::today();

        $service->checkIn($completed, $today->copy()->setTime(8, 0));
        $service->checkOut($completed, $today->copy()->setTime(15, 0));

        $service->checkIn($below, $today->copy()->setTime(8, 0));
        $service->checkOut($below, $today->copy()->setTime(12, 0));

        $service->checkIn($above, $today->copy()->setTime(8, 0));
        $service->checkOut($above, $today->copy()->setTime(16, 0));

        $service->checkIn($open, $today->copy()->setTime(10, 0));

        $this->leaveRequest($onLeave, LeaveRequestStatus::Approved);
        $this->leaveRequest($pendingLeave, LeaveRequestStatus::Pending);
        $this->leaveRequest($rejectedLeave, LeaveRequestStatus::Rejected);

        $inactive->update(['is_active' => false]);

        $overview = app(AdminDashboardService::class)->todayOverview($today);

        $this->assertSame(8, $overview['total_active_employees']);
        $this->assertSame(1, $overview['currently_working']);
        $this->assertSame(1, $overview['completed_today']);
        $this->assertSame(2, $overview['below_required_today']);
        $this->assertSame(1, $overview['above_required_today']);
        $this->assertSame(1, $overview['currently_on_leave']);
        $this->assertSame(0, $overview['absent_today']);
        $this->assertSame(3, $overview['not_checked_in_yet_today']);
        $this->assertSame(1, $overview['pending_leave_requests']);
        $this->assertSame(18900, $overview['average_working_seconds_today']);
        $this->assertSame('5h 15m', $overview['average_working_time_today']);
        $this->assertSame([
            'Completed' => 1,
            'Below Required' => 2,
            'Above Required' => 1,
            'Absent' => 0,
            'Not Checked In Yet' => 3,
            'On Leave' => 1,
        ], $overview['distribution']);

        $rows = collect(app(AdminDashboardService::class)->todayWorkforceRows($today));

        $this->assertSame('Not Checked In Yet', $rows->firstWhere('employee_id', $absent->id)['state']);
        $this->assertSame('On Leave', $rows->firstWhere('employee_id', $onLeave->id)['state']);
        $this->assertSame('Not Checked In Yet', $rows->firstWhere('employee_id', $pendingLeave->id)['state']);
        $this->assertSame('Not Checked In Yet', $rows->firstWhere('employee_id', $rejectedLeave->id)['state']);
    }

    public function test_open_attendance_session_contributes_to_workforce_rows(): void
    {
        $employee = $this->employee('Working Employee');

        app(AttendanceService::class)->checkIn($employee, Carbon::today()->setTime(10, 0));

        $rows = app(AdminDashboardService::class)->todayWorkforceRows(Carbon::today());
        $row = collect($rows)->firstWhere('employee_id', $employee->id);

        $this->assertSame('Working', $row['state']);
        $this->assertSame('2h 0m', $row['worked_today']);
        $this->assertSame('Below Required', $row['attendance_status']);
    }

    public function test_charts_return_empty_safe_data_when_no_attendance_exists(): void
    {
        $this->employee('No Attendance Employee');

        $service = app(AdminDashboardService::class);

        $this->assertTrue(collect($service->weeklyAverageWorkingHours()['datasets'][0]['data'])->every(fn (int|float $value): bool => $value === 0 || $value === 0.0));
        $this->assertTrue(collect($service->monthlyTotalWorkingHours()['datasets'][0]['data'])->every(fn (int|float $value): bool => $value === 0 || $value === 0.0));
        $this->assertSame([0.0], $service->employeeWorkingHoursCurrentMonth(limit: 1)['datasets'][0]['data']);
    }

    public function test_admin_dashboard_access_control(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        $this->get('/admin')->assertSuccessful();

        $this->actingAs($this->employeeUser, 'admin');

        $this->get('/admin')->assertForbidden();
    }

    private function employee(string $name, bool $isActive = true): Employee
    {
        $user = User::create([
            'name' => $name,
            'email' => str($name)->slug()->append('@test.com')->toString(),
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
            'department' => 'Operations',
            'job_title' => 'Staff',
            'is_active' => $isActive,
        ]);

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);

        return $employee;
    }

    private function leaveRequest(Employee $employee, LeaveRequestStatus $status): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annualType->id,
            'from_date' => Carbon::today()->toDateString(),
            'to_date' => Carbon::today()->toDateString(),
            'total_days' => 1,
            'reason' => 'Leave today',
            'status' => $status,
        ]);
    }
}
