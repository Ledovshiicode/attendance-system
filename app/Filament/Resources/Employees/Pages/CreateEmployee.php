<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Employee {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Employee,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_number' => $data['employee_number'],
                'department' => $data['department'],
                'job_title' => $data['job_title'],
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            LeaveBalance::create([
                'employee_id' => $employee->id,
                'annual_allowance' => 35,
                'used_days' => 0,
            ]);

            return $employee;
        });
    }
}
