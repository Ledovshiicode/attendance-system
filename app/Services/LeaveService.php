<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function submitRequest(
        Employee $employee,
        LeaveType $leaveType,
        Carbon $fromDate,
        Carbon $toDate,
        string $reason,
        ?string $attachmentPath = null,
    ): LeaveRequest {
        if (! $employee->is_active) {
            throw new \DomainException('Employee is not active.');
        }

        if (! $leaveType->is_active) {
            throw new \DomainException('Leave type is not active.');
        }

        if ($toDate->lt($fromDate)) {
            throw new \DomainException('End date must be on or after start date.');
        }

        if (blank($reason)) {
            throw new \DomainException('Reason must not be blank.');
        }

        if ($leaveType->requires_attachment && blank($attachmentPath)) {
            throw new \DomainException('This leave type requires an attachment.');
        }

        $this->checkOverlappingRequests($employee, $fromDate, $toDate);

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_days' => $this->calculateTotalDays($fromDate, $toDate),
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => LeaveRequestStatus::Pending,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approve(LeaveRequest $request, User $approver): LeaveRequest
    {
        if ($approver->role !== UserRole::Admin) {
            throw new \DomainException('Only admins can approve leave requests.');
        }

        return DB::transaction(function () use ($request, $approver) {
            $request = LeaveRequest::query()->where('id', $request->id)->lockForUpdate()->first();

            if ($request->status !== LeaveRequestStatus::Pending) {
                throw new \DomainException('Only pending requests can be approved.');
            }

            if ($request->leaveType->deducts_annual_balance) {
                $balance = $this->getOrCreateBalance($request->employee_id);

                if ($balance->remainingDays() < $request->total_days) {
                    throw new \DomainException('Insufficient annual leave balance.');
                }

                LeaveBalance::query()
                    ->where('id', $balance->id)
                    ->lockForUpdate()
                    ->increment('used_days', $request->total_days);
            }

            $request->update([
                'status' => LeaveRequestStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            return $request->fresh();
        });
    }

    public function reject(LeaveRequest $request, User $approver, ?string $reason): LeaveRequest
    {
        if ($approver->role !== UserRole::Admin) {
            throw new \DomainException('Only admins can reject leave requests.');
        }

        return DB::transaction(function () use ($request, $approver, $reason) {
            $request = LeaveRequest::query()->where('id', $request->id)->lockForUpdate()->first();

            if ($request->status !== LeaveRequestStatus::Pending) {
                throw new \DomainException('Only pending requests can be rejected.');
            }

            $request->update([
                'status' => LeaveRequestStatus::Rejected,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $request->fresh();
        });
    }

    public function getOrCreateBalance(int $employeeId): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['employee_id' => $employeeId],
            [
                'annual_allowance' => 35,
                'used_days' => 0,
            ],
        );
    }

    public function getBalance(Employee $employee): array
    {
        $balance = $this->getOrCreateBalance($employee->id);

        return [
            'annual_allowance' => $balance->annual_allowance,
            'used_days' => $balance->used_days,
            'remaining_days' => $balance->remainingDays(),
        ];
    }

    public function getLeaveSummary(Employee $employee): array
    {
        $balance = $this->getOrCreateBalance($employee->id);

        $counts = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total_allowance' => $balance->annual_allowance,
            'used_days' => $balance->used_days,
            'remaining_days' => $balance->remainingDays(),
            'pending_requests' => $counts->get(LeaveRequestStatus::Pending->value, 0),
            'approved_requests' => $counts->get(LeaveRequestStatus::Approved->value, 0),
            'rejected_requests' => $counts->get(LeaveRequestStatus::Rejected->value, 0),
        ];
    }

    public function calculateTotalDays(Carbon $fromDate, Carbon $toDate): int
    {
        return $fromDate->diffInDays($toDate) + 1;
    }

    private function checkOverlappingRequests(Employee $employee, Carbon $fromDate, Carbon $toDate): void
    {
        $overlapping = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->exists();

        if ($overlapping) {
            throw new \DomainException('Leave request overlaps with an existing pending or approved request.');
        }
    }
}
