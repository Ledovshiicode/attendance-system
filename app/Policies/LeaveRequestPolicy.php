<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin || $this->isActiveEmployee($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $this->isActiveEmployee($user)
            && $leaveRequest->employee_id === $user->employee?->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isActiveEmployee($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->role === UserRole::Admin;
    }

    private function isActiveEmployee(User $user): bool
    {
        return $user->role === UserRole::Employee
            && (bool) $user->employee?->is_active;
    }
}
