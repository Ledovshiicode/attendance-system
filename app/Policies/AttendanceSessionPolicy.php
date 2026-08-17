<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, AttendanceSession $attendanceSession): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AttendanceSession $attendanceSession): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceSession $attendanceSession): bool
    {
        return false;
    }

    public function restore(User $user, AttendanceSession $attendanceSession): bool
    {
        return false;
    }

    public function forceDelete(User $user, AttendanceSession $attendanceSession): bool
    {
        return false;
    }
}
