<?php

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Attendance;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelOperationalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $employeeUser;

    protected Employee $employee;

    protected Employee $otherEmployee;

    protected LeaveType $annualType;

    protected LeaveType $emergencyType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->annualType = LeaveType::create([
            'name' => 'Annual',
            'deducts_annual_balance' => true,
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        $this->emergencyType = LeaveType::create([
            'name' => 'Emergency',
            'deducts_annual_balance' => false,
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
            'name' => 'Employee One',
            'email' => 'employee1@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_number' => 'EMP001',
            'department' => 'Engineering',
            'job_title' => 'Developer',
            'phone' => '123456',
            'hire_date' => '2026-01-01',
            'is_active' => true,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);

        $otherUser = User::create([
            'name' => 'Employee Two',
            'email' => 'employee2@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $this->otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'employee_number' => 'EMP002',
            'department' => 'Support',
            'job_title' => 'Agent',
            'is_active' => true,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->otherEmployee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs($this->adminUser, 'admin');

        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }

    public function test_admin_can_access_employee_list(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/employees')->assertSuccessful();

        Livewire::test(ListEmployees::class)
            ->assertCanSeeTableRecords([$this->employee, $this->otherEmployee]);
    }

    public function test_employee_cannot_access_admin_employee_resource(): void
    {
        $this->actingAs($this->employeeUser, 'admin');

        $this->get('/admin/employees')->assertForbidden();
    }

    public function test_admin_can_create_employee_with_user_and_leave_balance(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'name' => 'New Employee',
                'email' => 'new.employee@test.com',
                'password' => 'password',
                'employee_number' => 'EMP100',
                'department' => 'HR',
                'job_title' => 'Coordinator',
                'phone' => '555-000',
                'hire_date' => '2026-08-17',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new.employee@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Employee, $user->role);

        $employee = Employee::where('employee_number', 'EMP100')->first();
        $this->assertNotNull($employee);
        $this->assertSame($user->id, $employee->user_id);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $employee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);
    }

    public function test_admin_can_deactivate_employee_and_history_remains_without_delete_action(): void
    {
        app(AttendanceService::class)->checkIn($this->employee, Carbon::today()->setTime(8, 0));
        app(AttendanceService::class)->checkOut($this->employee, Carbon::today()->setTime(10, 0));

        $this->actingAsAdmin();

        Livewire::test(ListEmployees::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($this->employee))
            ->callAction(TestAction::make('deactivate')->table($this->employee));

        $this->assertFalse($this->employee->fresh()->is_active);
        $this->assertSame(1, $this->employee->attendanceSessions()->count());
    }

    public function test_admin_can_see_multiple_employees_attendance_and_inspect_sessions(): void
    {
        $service = app(AttendanceService::class);
        $date = Carbon::today();

        $service->checkIn($this->employee, $date->copy()->setTime(8, 0));
        $service->checkOut($this->employee, $date->copy()->setTime(12, 0));

        $service->checkIn($this->otherEmployee, $date->copy()->setTime(9, 0));
        $service->checkOut($this->otherEmployee, $date->copy()->setTime(16, 30));

        $this->actingAsAdmin();

        Livewire::test(Attendance::class)
            ->assertSee('Employee One')
            ->assertSee('Employee Two')
            ->assertSee('4h 0m')
            ->assertSee('7h 30m')
            ->assertSee('View')
            ->call('viewSessions', $this->employee->id)
            ->assertSee('08:00 AM')
            ->assertSee('12:00 PM');
    }

    public function test_employee_cannot_access_admin_attendance_page(): void
    {
        $this->actingAs($this->employeeUser, 'admin');

        $this->get('/admin/attendance')->assertForbidden();
    }

    public function test_currently_working_employee_is_identified(): void
    {
        app(AttendanceService::class)->checkIn($this->employee, Carbon::now()->subHour());

        $this->actingAsAdmin();

        Livewire::test(Attendance::class)
            ->assertSee('Working')
            ->call('viewSessions', $this->employee->id)
            ->assertSee('Now');
    }

    public function test_admin_sees_all_leave_requests(): void
    {
        $ownRequest = $this->createLeaveRequest($this->employee, $this->annualType, 'Engineering vacation');
        $otherRequest = $this->createLeaveRequest($this->otherEmployee, $this->emergencyType, 'Support emergency');

        $this->actingAsAdmin();

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords([$ownRequest, $otherRequest]);
    }

    public function test_admin_can_approve_leave_request_through_filament_action(): void
    {
        $request = $this->createLeaveRequest($this->employee, $this->annualType, 'Annual leave');

        $this->actingAsAdmin();

        Livewire::test(ListLeaveRequests::class)
            ->callAction(TestAction::make('approve')->table($request));

        $request->refresh();
        $this->assertSame(LeaveRequestStatus::Approved, $request->status);
        $this->assertSame($this->adminUser->id, $request->approved_by);
        $this->assertSame(5, $this->employee->leaveBalance->fresh()->used_days);
    }

    public function test_admin_can_reject_leave_request_without_deducting_balance(): void
    {
        $request = $this->createLeaveRequest($this->employee, $this->annualType, 'Annual leave');

        $this->actingAsAdmin();

        Livewire::test(ListLeaveRequests::class)
            ->callAction(TestAction::make('reject')->table($request), data: [
                'rejection_reason' => 'Coverage unavailable',
            ]);

        $request->refresh();
        $this->assertSame(LeaveRequestStatus::Rejected, $request->status);
        $this->assertSame('Coverage unavailable', $request->rejection_reason);
        $this->assertSame(0, $this->employee->leaveBalance->fresh()->used_days);
    }

    public function test_approved_request_cannot_be_approved_or_rejected_again(): void
    {
        $request = $this->createLeaveRequest($this->employee, $this->annualType, 'Annual leave');
        app(LeaveService::class)->approve($request, $this->adminUser);

        $this->actingAsAdmin();

        Livewire::test(ListLeaveRequests::class)
            ->assertActionHidden(TestAction::make('approve')->table($request->fresh()))
            ->assertActionHidden(TestAction::make('reject')->table($request->fresh()));
    }

    public function test_employee_cannot_access_admin_leave_resource_or_direct_url(): void
    {
        $request = $this->createLeaveRequest($this->otherEmployee, $this->annualType, 'Other leave');

        $this->actingAs($this->employeeUser, 'admin');

        $this->get('/admin/leave-requests')->assertForbidden();
        $this->get('/admin/leave-requests/'.$request->id)->assertForbidden();
    }

    private function createLeaveRequest(Employee $employee, LeaveType $leaveType, string $reason): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-05',
            'total_days' => 5,
            'reason' => $reason,
            'status' => LeaveRequestStatus::Pending,
        ]);
    }
}
