<?php

namespace Database\Seeders;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ],
        );

        $employees = $this->seedEmployees();

        $this->seedAttendance($employees);
        $this->seedLeaveRequests($employees, $admin);
    }

    /**
     * @return array<string, Employee>
     */
    private function seedEmployees(): array
    {
        $records = [
            ['key' => 'alex', 'number' => 'EMP001', 'name' => 'Alex Morgan', 'email' => 'employee@example.com', 'title' => 'Software Developer', 'department' => 'IT', 'phone' => '+1 555 0101', 'hire_date' => '2023-02-13', 'active' => true],
            ['key' => 'priya', 'number' => 'EMP002', 'name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'title' => 'HR Specialist', 'department' => 'HR', 'phone' => '+1 555 0102', 'hire_date' => '2022-09-05', 'active' => true],
            ['key' => 'marcus', 'number' => 'EMP003', 'name' => 'Marcus Chen', 'email' => 'marcus.chen@example.com', 'title' => 'Sales Executive', 'department' => 'Sales', 'phone' => '+1 555 0103', 'hire_date' => '2021-06-21', 'active' => true],
            ['key' => 'sofia', 'number' => 'EMP004', 'name' => 'Sofia Bennett', 'email' => 'sofia.bennett@example.com', 'title' => 'Operations Coordinator', 'department' => 'Operations', 'phone' => '+1 555 0104', 'hire_date' => '2020-11-02', 'active' => true],
            ['key' => 'nina', 'number' => 'EMP005', 'name' => 'Nina Patel', 'email' => 'nina.patel@example.com', 'title' => 'Finance Analyst', 'department' => 'Finance', 'phone' => '+1 555 0105', 'hire_date' => '2024-01-15', 'active' => true],
            ['key' => 'diego', 'number' => 'EMP006', 'name' => 'Diego Ramirez', 'email' => 'diego.ramirez@example.com', 'title' => 'Systems Administrator', 'department' => 'IT', 'phone' => '+1 555 0106', 'hire_date' => '2022-04-18', 'active' => true],
            ['key' => 'hannah', 'number' => 'EMP007', 'name' => 'Hannah Reed', 'email' => 'hannah.reed@example.com', 'title' => 'Recruiter', 'department' => 'HR', 'phone' => '+1 555 0107', 'hire_date' => '2023-07-10', 'active' => true],
            ['key' => 'omar', 'number' => 'EMP008', 'name' => 'Omar Wilson', 'email' => 'omar.wilson@example.com', 'title' => 'Account Manager', 'department' => 'Sales', 'phone' => '+1 555 0108', 'hire_date' => '2021-12-01', 'active' => true],
            ['key' => 'emma', 'number' => 'EMP009', 'name' => 'Emma Johnson', 'email' => 'emma.johnson@example.com', 'title' => 'Logistics Lead', 'department' => 'Operations', 'phone' => '+1 555 0109', 'hire_date' => '2020-03-23', 'active' => true],
            ['key' => 'liam', 'number' => 'EMP010', 'name' => 'Liam Carter', 'email' => 'liam.carter@example.com', 'title' => 'Finance Coordinator', 'department' => 'Finance', 'phone' => '+1 555 0110', 'hire_date' => '2022-08-29', 'active' => true],
            ['key' => 'grace', 'number' => 'EMP011', 'name' => 'Grace Kim', 'email' => 'grace.kim@example.com', 'title' => 'QA Engineer', 'department' => 'IT', 'phone' => '+1 555 0111', 'hire_date' => '2019-10-07', 'active' => false],
        ];

        $employees = [];

        foreach ($records as $record) {
            $user = User::updateOrCreate(
                ['email' => $record['email']],
                [
                    'name' => $record['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Employee,
                ],
            );

            $employee = Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_number' => $record['number'],
                    'job_title' => $record['title'],
                    'department' => $record['department'],
                    'phone' => $record['phone'],
                    'hire_date' => Carbon::parse($record['hire_date'])->toDateString(),
                    'is_active' => $record['active'],
                ],
            );

            LeaveBalance::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'annual_allowance' => 35,
                    'used_days' => 0,
                ],
            );

            $employees[$record['key']] = $employee;
        }

        return $employees;
    }

    /**
     * @param  array<string, Employee>  $employees
     */
    private function seedAttendance(array $employees): void
    {
        AttendanceSession::query()->delete();

        $patterns = [
            'alex' => ['exact', 'split', 'above', 'below', 'exact', 'split', 'absent'],
            'priya' => ['below', 'exact', 'below', 'split', 'exact', 'absent', 'above'],
            'marcus' => ['above', 'above', 'exact', 'below', 'split', 'above', 'absent'],
            'sofia' => ['split', 'exact', 'above', 'exact', 'below', 'split', 'above'],
            'nina' => ['absent', 'below', 'exact', 'below', 'split', 'exact', 'above'],
            'diego' => ['exact', 'above', 'split', 'above', 'exact', 'below', 'split'],
            'hannah' => ['below', 'absent', 'exact', 'split', 'below', 'above', 'exact'],
            'omar' => ['above', 'split', 'below', 'exact', 'above', 'split', 'below'],
            'emma' => ['split', 'below', 'above', 'exact', 'split', 'absent', 'exact'],
            'liam' => ['exact', 'below', 'absent', 'above', 'exact', 'below', 'split'],
            'grace' => ['exact', 'above', 'below', 'split', 'absent', 'exact', 'below'],
        ];

        for ($daysAgo = 29; $daysAgo >= 1; $daysAgo--) {
            $date = now()->timezone(config('app.timezone'))->subDays($daysAgo);

            foreach ($employees as $key => $employee) {
                if (! $employee->is_active && $daysAgo < 10) {
                    continue;
                }

                $pattern = $patterns[$key][$daysAgo % 7];
                $this->seedAttendancePattern($employee, $date, $pattern);
            }
        }

        $today = now()->timezone(config('app.timezone'))->startOfDay();

        $this->seedAttendancePattern($employees['alex'], $today, 'split');
        $this->seedAttendancePattern($employees['marcus'], $today, 'below');
        $this->seedAttendancePattern($employees['sofia'], $today, 'above');
        $this->seedAttendancePattern($employees['nina'], $today, 'open');
        $this->seedAttendancePattern($employees['diego'], $today, 'exact');
        $this->seedAttendancePattern($employees['hannah'], $today, 'below');
        $this->seedAttendancePattern($employees['omar'], $today, 'above');
        $this->seedAttendancePattern($employees['emma'], $today, 'split');
        $this->seedAttendancePattern($employees['grace'], $today->copy()->subDays(12), 'above');
    }

    private function seedAttendancePattern(Employee $employee, Carbon $date, string $pattern): void
    {
        match ($pattern) {
            'exact' => $this->seedClosedSession($employee, $date, '09:00', '16:00'),
            'below' => $this->seedClosedSession($employee, $date, '09:15', '15:00'),
            'above' => $this->seedClosedSession($employee, $date, '08:30', '17:45'),
            'split' => $this->seedSplitSessions($employee, $date),
            'open' => $this->seedOpenSession($employee, $date),
            default => null,
        };
    }

    private function seedSplitSessions(Employee $employee, Carbon $date): void
    {
        $this->seedClosedSession($employee, $date, '08:45', '12:00');
        $this->seedClosedSession($employee, $date, '13:00', '16:45');
    }

    private function seedClosedSession(Employee $employee, Carbon $date, string $checkInTime, string $checkOutTime): void
    {
        $calculator = app(AttendanceCalculator::class);
        $checkInAt = Carbon::parse($date->toDateString().' '.$checkInTime);
        $checkOutAt = Carbon::parse($date->toDateString().' '.$checkOutTime);

        AttendanceSession::create([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'counted_seconds' => $calculator->calculateSessionSeconds($checkInAt, $checkOutAt, $date),
        ]);
    }

    private function seedOpenSession(Employee $employee, Carbon $date): void
    {
        $checkInAt = now()->timezone(config('app.timezone'))->subHours(2);

        if (! $checkInAt->isSameDay($date)) {
            $checkInAt = $date->copy()->setTime(8, 30);
        }

        AttendanceSession::create([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'check_in_at' => $checkInAt,
            'check_out_at' => null,
            'counted_seconds' => 0,
        ]);
    }

    /**
     * @param  array<string, Employee>  $employees
     */
    private function seedLeaveRequests(array $employees, User $admin): void
    {
        LeaveRequest::query()->delete();
        LeaveBalance::query()->update(['used_days' => 0]);

        Storage::disk('local')->put('leave-documents/demo-medical-note.pdf', 'Demo medical note placeholder for seeded sick leave.');

        $annual = LeaveType::where('name', 'Annual')->firstOrFail();
        $sick = LeaveType::where('name', 'Sick')->firstOrFail();
        $emergency = LeaveType::where('name', 'Emergency')->firstOrFail();
        $service = app(LeaveService::class);

        $today = now()->timezone(config('app.timezone'))->startOfDay();

        $this->approveRequest($service, $admin, $employees['alex'], $annual, $today->copy()->subDays(18), $today->copy()->subDays(16), 'Family vacation approved in advance.');
        $this->approveRequest($service, $admin, $employees['alex'], $sick, $today->copy()->subDays(9), $today->copy()->subDays(9), 'Medical appointment follow-up.', 'leave-documents/demo-medical-note.pdf');
        $this->submitRequest($service, $employees['alex'], $annual, $today->copy()->addDays(8), $today->copy()->addDays(10), 'Planned long weekend with family.');
        $this->rejectRequest($service, $admin, $employees['alex'], $annual, $today->copy()->subDays(3), $today->copy()->subDays(2), 'Requested during production release window.', 'Release coverage required.');

        $this->approveRequest($service, $admin, $employees['priya'], $annual, $today->copy(), $today->copy()->addDay(), 'Approved annual leave for personal travel.');
        $this->submitRequest($service, $employees['marcus'], $annual, $today->copy()->addDays(14), $today->copy()->addDays(16), 'Pending summer break request.');
        $this->rejectRequest($service, $admin, $employees['sofia'], $annual, $today->copy()->subDays(11), $today->copy()->subDays(10), 'Conflicted with operations handover.', 'Operations coverage was too low.');
        $this->approveRequest($service, $admin, $employees['nina'], $sick, $today->copy()->subDays(6), $today->copy()->subDays(6), 'Approved sick leave.', 'leave-documents/demo-medical-note.pdf');
        $this->approveRequest($service, $admin, $employees['omar'], $emergency, $today->copy()->subDays(4), $today->copy()->subDays(4), 'Emergency family matter.');
        $this->submitRequest($service, $employees['liam'], $emergency, $today->copy()->addDays(3), $today->copy()->addDays(3), 'Pending urgent appointment request.');
    }

    private function submitRequest(LeaveService $service, Employee $employee, LeaveType $type, Carbon $from, Carbon $to, string $reason, ?string $attachment = null): LeaveRequest
    {
        return $service->submitRequest($employee, $type, $from, $to, $reason, $attachment);
    }

    private function approveRequest(LeaveService $service, User $admin, Employee $employee, LeaveType $type, Carbon $from, Carbon $to, string $reason, ?string $attachment = null): LeaveRequest
    {
        return $service->approve(
            $this->submitRequest($service, $employee, $type, $from, $to, $reason, $attachment),
            $admin,
        );
    }

    private function rejectRequest(LeaveService $service, User $admin, Employee $employee, LeaveType $type, Carbon $from, Carbon $to, string $reason, string $rejectionReason): LeaveRequest
    {
        $request = $this->submitRequest($service, $employee, $type, $from, $to, $reason);

        if ($request->status !== LeaveRequestStatus::Pending) {
            return $request;
        }

        return $service->reject($request, $admin, $rejectionReason);
    }
}
