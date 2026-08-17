<?php

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Filament\Employee\Pages\AttendanceHistory;
use App\Filament\Employee\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Employee\Resources\LeaveRequestResource\Pages\ListLeaveRequests;
use App\Filament\Employee\Widgets\AttendanceActionWidget;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeePanelTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $employee;

    protected User $employeeUser;

    protected User $adminUser;

    protected Employee $otherEmployee;

    protected User $otherEmployeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveType::insert([
            ['name' => 'Annual', 'deducts_annual_balance' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sick', 'deducts_annual_balance' => false, 'requires_attachment' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Emergency', 'deducts_annual_balance' => false, 'requires_attachment' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->employeeUser = User::create([
            'name' => 'Employee',
            'email' => 'employee@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_number' => 'EMP001',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => true,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);

        $this->otherEmployeeUser = User::create([
            'name' => 'Other Employee',
            'email' => 'other@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $this->otherEmployee = Employee::create([
            'user_id' => $this->otherEmployeeUser->id,
            'employee_number' => 'EMP002',
            'job_title' => 'Designer',
            'department' => 'Design',
            'is_active' => true,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->otherEmployee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);
    }

    private function actingAsEmployee(): void
    {
        $this->actingAs($this->employeeUser, 'employee');

        Filament::setCurrentPanel('employee');
        Filament::bootCurrentPanel();
    }

    // ─── DASHBOARD ACCESS ───

    public function test_employee_can_access_dashboard(): void
    {
        $this->actingAsEmployee();

        $response = $this->get('/employee');

        $response->assertSuccessful();
    }

    public function test_admin_cannot_access_employee_dashboard(): void
    {
        $this->actingAs($this->adminUser, 'employee');

        $response = $this->get('/employee');

        $response->assertForbidden();
    }

    // ─── ATTENDANCE HISTORY ───

    public function test_employee_can_access_attendance_history(): void
    {
        $this->actingAsEmployee();

        $response = $this->get('/employee/attendance');

        $response->assertSuccessful();
    }

    public function test_employee_cannot_view_other_employee_attendance_record(): void
    {
        $service = app(AttendanceService::class);
        $date = Carbon::today();

        $service->checkIn($this->employee, $date->copy()->setTime(8, 30));
        $service->checkOut($this->employee, $date->copy()->setTime(10, 30));

        $service->checkIn($this->otherEmployee, $date->copy()->setTime(8, 0));
        $service->checkOut($this->otherEmployee, $date->copy()->setTime(12, 0));

        $this->actingAsEmployee();

        Livewire::test(AttendanceHistory::class)
            ->assertSet('days.0.sessions.0.duration', '2h 0m')
            ->assertDontSee('4h 0m');
    }

    // ─── CHECK IN / CHECK OUT ───

    public function test_check_in_action_creates_session(): void
    {
        $this->actingAsEmployee();

        $component = Livewire::test(AttendanceActionWidget::class);

        $component->call('checkIn');

        $openSession = $this->employee->attendanceSessions()->whereNull('check_out_at')->first();
        $this->assertNotNull($openSession);
    }

    public function test_check_out_action_closes_session(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::now()->subHours(2));

        $this->actingAsEmployee();

        $component = Livewire::test(AttendanceActionWidget::class);

        $component->call('checkOut');

        $openSession = $this->employee->attendanceSessions()->whereNull('check_out_at')->first();
        $this->assertNull($openSession);
    }

    // ─── LEAVE REQUEST RESOURCE ───

    public function test_leave_request_resource_only_shows_own_requests(): void
    {
        $annualType = LeaveType::where('name', 'Annual')->first();

        $otherEmployeeRequest = LeaveRequest::create([
            'employee_id' => $this->otherEmployee->id,
            'leave_type_id' => $annualType->id,
            'from_date' => '2026-08-20',
            'to_date' => '2026-08-24',
            'total_days' => 5,
            'reason' => 'Other vacation',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $ownRequest = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $annualType->id,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-05',
            'total_days' => 5,
            'reason' => 'My vacation',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->actingAsEmployee();

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords([$ownRequest])
            ->assertCanNotSeeTableRecords([$otherEmployeeRequest]);
    }

    public function test_employee_can_access_leave_request_create_page(): void
    {
        $this->actingAsEmployee();

        $response = $this->get('/employee/leave-requests/create');
        $response->assertSuccessful();
    }

    public function test_employee_can_submit_leave_request(): void
    {
        $this->actingAsEmployee();

        $annualType = LeaveType::where('name', 'Annual')->first();
        $balance = $this->employee->leaveBalance;

        Livewire::test(CreateLeaveRequest::class)
            ->fillForm([
                'leave_type_id' => $annualType->id,
                'from_date' => '2026-10-01',
                'to_date' => '2026-10-05',
                'reason' => 'Fall vacation',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/employee/leave-requests');

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee->id,
            'reason' => 'Fall vacation',
            'status' => 'pending',
            'total_days' => 5,
        ]);

        $this->assertSame($balance->used_days, $balance->fresh()->used_days);
    }

    public function test_employee_cannot_access_other_employee_leave_request(): void
    {
        $annualType = LeaveType::where('name', 'Annual')->first();

        $otherRequest = LeaveRequest::create([
            'employee_id' => $this->otherEmployee->id,
            'leave_type_id' => $annualType->id,
            'from_date' => '2026-08-20',
            'to_date' => '2026-08-24',
            'total_days' => 5,
            'reason' => 'Other vacation',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->actingAsEmployee();

        $response = $this->get('/employee/leave-requests/'.$otherRequest->id);
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
        $response->assertDontSee('Other vacation');
    }
}
