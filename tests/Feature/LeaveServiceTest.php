<?php

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeaveService $service;

    protected LeaveType $annualType;

    protected LeaveType $sickType;

    protected LeaveType $emergencyType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LeaveService;

        LeaveType::insert([
            ['name' => 'Annual', 'deducts_annual_balance' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sick', 'deducts_annual_balance' => false, 'requires_attachment' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Emergency', 'deducts_annual_balance' => false, 'requires_attachment' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->annualType = LeaveType::where('name', 'Annual')->first();
        $this->sickType = LeaveType::where('name', 'Sick')->first();
        $this->emergencyType = LeaveType::where('name', 'Emergency')->first();
    }

    private function createActiveEmployee(): Employee
    {
        $user = User::create([
            'name' => 'Employee',
            'email' => 'employee@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP001',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => true,
        ]);

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'annual_allowance' => 35,
            'used_days' => 0,
        ]);

        return $employee;
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

    private function createEmployeeUser(): User
    {
        return User::create([
            'name' => 'Employee User',
            'email' => 'empuser@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);
    }

    // ─── REQUEST CREATION TESTS ───

    public function test_active_employee_can_submit_leave_request(): void
    {
        $employee = $this->createActiveEmployee();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->assertNotNull($request);
        $this->assertInstanceOf(LeaveRequest::class, $request);
    }

    public function test_new_request_is_pending(): void
    {
        $employee = $this->createActiveEmployee();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->assertSame(LeaveRequestStatus::Pending, $request->status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_at);
        $this->assertNull($request->rejection_reason);
    }

    public function test_request_does_not_deduct_balance(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(0, $balance->used_days);
    }

    public function test_same_day_leave_equals_one_day(): void
    {
        $employee = $this->createActiveEmployee();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-20'),
            'Day off',
        );

        $this->assertSame(1, $request->total_days);
    }

    public function test_aug_20_to_aug_24_equals_five_days(): void
    {
        $employee = $this->createActiveEmployee();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->assertSame(5, $request->total_days);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('End date must be on or after start date');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-24'),
            Carbon::parse('2026-08-20'),
            'Vacation',
        );
    }

    public function test_inactive_employee_cannot_submit(): void
    {
        $employee = $this->createInactiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Employee is not active');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );
    }

    public function test_inactive_leave_type_cannot_be_used(): void
    {
        $employee = $this->createActiveEmployee();
        $this->annualType->update(['is_active' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Leave type is not active');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );
    }

    public function test_blank_reason_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Reason must not be blank');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            '',
        );
    }

    public function test_attachment_required_type_fails_without_attachment(): void
    {
        $employee = $this->createActiveEmployee();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('requires an attachment');

        $this->service->submitRequest(
            $employee,
            $this->sickType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Sick leave',
        );
    }

    public function test_attachment_required_type_succeeds_with_attachment(): void
    {
        $employee = $this->createActiveEmployee();

        $request = $this->service->submitRequest(
            $employee,
            $this->sickType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Sick leave',
            'attachments/medical.pdf',
        );

        $this->assertNotNull($request);
        $this->assertSame('attachments/medical.pdf', $request->attachment_path);
    }

    public function test_overlapping_pending_request_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('overlaps');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-22'),
            Carbon::parse('2026-08-26'),
            'More vacation',
        );
    }

    public function test_overlapping_approved_request_is_rejected(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->approve($request, $admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('overlaps');

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-22'),
            Carbon::parse('2026-08-26'),
            'More vacation',
        );
    }

    public function test_overlapping_rejected_request_does_not_block(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->reject($request, $admin, 'Denied');

        $newRequest = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-22'),
            Carbon::parse('2026-08-26'),
            'More vacation',
        );

        $this->assertNotNull($newRequest);
        $this->assertSame(LeaveRequestStatus::Pending, $newRequest->status);
    }

    // ─── APPROVAL TESTS ───

    public function test_approval_updates_balance_correctly(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->approve($request, $admin);

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(35, $balance->annual_allowance);
        $this->assertSame(5, $balance->used_days);
        $this->assertSame(30, $balance->remainingDays());
    }

    public function test_pending_annual_leave_does_not_deduct(): void
    {
        $employee = $this->createActiveEmployee();

        $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(0, $balance->used_days);
        $this->assertSame(35, $balance->remainingDays());
    }

    public function test_rejected_annual_leave_does_not_deduct(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->reject($request, $admin, 'Denied');

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(0, $balance->used_days);
        $this->assertSame(35, $balance->remainingDays());
    }

    public function test_sick_leave_does_not_change_annual_balance(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->sickType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Sick leave',
            'attachments/medical.pdf',
        );

        $this->service->approve($request, $admin);

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(0, $balance->used_days);
        $this->assertSame(35, $balance->remainingDays());
    }

    public function test_insufficient_balance_rejects_approval(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        LeaveBalance::where('employee_id', $employee->id)->update(['used_days' => 33]);

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient annual leave balance');

        $this->service->approve($request, $admin);

        $request->refresh();
        $this->assertSame(LeaveRequestStatus::Pending, $request->status);

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(33, $balance->used_days);
    }

    public function test_already_approved_cannot_be_approved_again(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->approve($request, $admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only pending requests can be approved');

        $this->service->approve($request->fresh(), $admin);
    }

    public function test_already_rejected_cannot_be_approved(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->reject($request, $admin, 'Denied');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only pending requests can be approved');

        $this->service->approve($request->fresh(), $admin);
    }

    public function test_non_admin_cannot_approve(): void
    {
        $employee = $this->createActiveEmployee();
        $empUser = $this->createEmployeeUser();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only admins can approve');

        $this->service->approve($request, $empUser);
    }

    public function test_approval_records_metadata(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $approved = $this->service->approve($request, $admin);

        $this->assertSame(LeaveRequestStatus::Approved, $approved->status);
        $this->assertSame($admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertNull($approved->rejection_reason);
    }

    public function test_request_cannot_deduct_twice(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->approve($request, $admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only pending requests can be approved');

        $this->service->approve($request->fresh(), $admin);

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(5, $balance->used_days);
    }

    // ─── REJECTION TESTS ───

    public function test_admin_can_reject_pending_request(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $rejected = $this->service->reject($request, $admin, 'Not enough coverage');

        $this->assertSame(LeaveRequestStatus::Rejected, $rejected->status);
    }

    public function test_rejection_stores_reason(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $rejected = $this->service->reject($request, $admin, 'Team busy period');

        $this->assertSame('Team busy period', $rejected->rejection_reason);
    }

    public function test_rejection_records_decision_metadata(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $rejected = $this->service->reject($request, $admin, 'Denied');

        $this->assertSame($admin->id, $rejected->approved_by);
        $this->assertNotNull($rejected->approved_at);
    }

    public function test_rejection_does_not_deduct_annual_balance(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->reject($request, $admin, 'Denied');

        $balance = LeaveBalance::where('employee_id', $employee->id)->first();
        $this->assertSame(0, $balance->used_days);
    }

    public function test_approved_request_cannot_be_rejected(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->approve($request, $admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only pending requests can be rejected');

        $this->service->reject($request->fresh(), $admin, 'Changed mind');
    }

    public function test_rejected_request_cannot_be_rejected_twice(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->service->reject($request, $admin, 'First rejection');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only pending requests can be rejected');

        $this->service->reject($request->fresh(), $admin, 'Second rejection');
    }

    public function test_non_admin_cannot_reject(): void
    {
        $employee = $this->createActiveEmployee();
        $empUser = $this->createEmployeeUser();

        $request = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-24'),
            'Vacation',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only admins can reject');

        $this->service->reject($request, $empUser, 'No');
    }

    // ─── BALANCE TESTS ───

    public function test_get_balance_returns_correct_values(): void
    {
        $employee = $this->createActiveEmployee();

        $balance = $this->service->getBalance($employee);

        $this->assertSame(35, $balance['annual_allowance']);
        $this->assertSame(0, $balance['used_days']);
        $this->assertSame(35, $balance['remaining_days']);
    }

    public function test_get_leave_summary_returns_correct_counts(): void
    {
        $employee = $this->createActiveEmployee();
        $admin = $this->createAdmin();

        $request1 = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-22'),
            'Vacation 1',
        );

        $request2 = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-03'),
            'Vacation 2',
        );

        $request3 = $this->service->submitRequest(
            $employee,
            $this->annualType,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-03'),
            'Vacation 3',
        );

        $this->service->approve($request1, $admin);
        $this->service->reject($request2, $admin, 'Denied');

        $summary = $this->service->getLeaveSummary($employee);

        $this->assertSame(35, $summary['total_allowance']);
        $this->assertSame(3, $summary['used_days']);
        $this->assertSame(32, $summary['remaining_days']);
        $this->assertSame(1, $summary['pending_requests']);
        $this->assertSame(1, $summary['approved_requests']);
        $this->assertSame(1, $summary['rejected_requests']);
    }
}
