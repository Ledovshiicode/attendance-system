<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);
    }

    private function createActiveEmployee(): User
    {
        $user = User::create([
            'name' => 'Employee',
            'email' => 'employee@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP001',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => true,
        ]);

        return $user;
    }

    private function createInactiveEmployee(): User
    {
        $user = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP002',
            'job_title' => 'Developer',
            'department' => 'IT',
            'is_active' => false,
        ]);

        return $user;
    }

    private function createEmployeeWithoutProfile(): User
    {
        return User::create([
            'name' => 'No Profile',
            'email' => 'noprofile@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Employee,
        ]);
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response->assertSuccessful();
    }

    public function test_admin_cannot_access_employee_panel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'employee')->get('/employee');

        $response->assertForbidden();
    }

    public function test_active_employee_can_access_employee_panel(): void
    {
        $employee = $this->createActiveEmployee();

        $response = $this->actingAs($employee, 'employee')->get('/employee');

        $response->assertSuccessful();
    }

    public function test_active_employee_cannot_access_admin_panel(): void
    {
        $employee = $this->createActiveEmployee();

        $response = $this->actingAs($employee, 'admin')->get('/admin');

        $response->assertForbidden();
    }

    public function test_employee_without_profile_cannot_access_employee_panel(): void
    {
        $user = $this->createEmployeeWithoutProfile();

        $response = $this->actingAs($user, 'employee')->get('/employee');

        $response->assertForbidden();
    }

    public function test_inactive_employee_cannot_access_employee_panel(): void
    {
        $user = $this->createInactiveEmployee();

        $response = $this->actingAs($user, 'employee')->get('/employee');

        $response->assertForbidden();
    }

    public function test_admin_and_employee_guards_can_remain_authenticated_simultaneously(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createActiveEmployee();

        Auth::guard('admin')->login($admin);
        Auth::guard('employee')->login($employee);

        $this->assertTrue(Auth::guard('admin')->user()->is($admin));
        $this->assertTrue(Auth::guard('employee')->user()->is($employee));

        $this->get('/admin')->assertSuccessful();

        $this->assertTrue(Auth::guard('employee')->user()->is($employee));

        $this->get('/employee')->assertSuccessful();

        $this->assertTrue(Auth::guard('admin')->user()->is($admin));
    }

    public function test_logging_out_admin_guard_does_not_log_out_employee_guard(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createActiveEmployee();

        Auth::guard('admin')->login($admin);
        Auth::guard('employee')->login($employee);

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertTrue(Auth::guard('admin')->guest());
        $this->assertTrue(Auth::guard('employee')->user()->is($employee));

        $this->get('/employee')->assertSuccessful();
    }

    public function test_logging_out_employee_guard_does_not_log_out_admin_guard(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createActiveEmployee();

        Auth::guard('admin')->login($admin);
        Auth::guard('employee')->login($employee);

        $this->post('/employee/logout')->assertRedirect('/employee/login');

        $this->assertTrue(Auth::guard('employee')->guest());
        $this->assertTrue(Auth::guard('admin')->user()->is($admin));

        $this->get('/admin')->assertSuccessful();
    }
}
