<?php

namespace App\Services;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkState;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        protected AttendanceCalculator $calculator,
    ) {}

    public function checkIn(Employee $employee, ?Carbon $now = null): AttendanceSession
    {
        return DB::transaction(function () use ($employee, $now) {
            $employee = Employee::query()->where('id', $employee->id)->lockForUpdate()->first();

            if (! $employee->is_active) {
                throw new \DomainException('Employee is not active.');
            }

            $now = $this->localTime($now ?? now());

            $openSession = AttendanceSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->first();

            if ($openSession) {
                throw new \DomainException('Employee already has an open attendance session.');
            }

            return AttendanceSession::create([
                'employee_id' => $employee->id,
                'work_date' => $now->toDateString(),
                'check_in_at' => $now,
                'check_out_at' => null,
                'counted_seconds' => 0,
                'source' => AttendanceSource::Employee,
            ]);
        });
    }

    public function checkOut(Employee $employee, ?Carbon $now = null): AttendanceSession
    {
        return DB::transaction(function () use ($employee, $now) {
            $employee = Employee::query()->where('id', $employee->id)->lockForUpdate()->first();

            $now = $this->localTime($now ?? now());

            $openSession = AttendanceSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->first();

            if (! $openSession) {
                throw new \DomainException('No open attendance session found.');
            }

            if ($now->lte($openSession->check_in_at)) {
                throw new \DomainException('Check-out time must be after check-in time.');
            }

            $countedSeconds = $this->calculator->calculateSessionSeconds(
                $openSession->check_in_at,
                $now,
                $openSession->work_date,
            );

            $openSession->update([
                'check_out_at' => $now,
                'counted_seconds' => $countedSeconds,
            ]);

            return $openSession->fresh();
        });
    }

    public function getDailySummary(Employee $employee, Carbon $date, ?Carbon $now = null): array
    {
        $date = $this->localTime($date);
        $workDate = $date->toDateString();

        $sessions = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->get();

        return $this->getDailySummaryFromSessions(
            $employee,
            $date,
            $sessions,
            $this->hasApprovedLeave($employee, $date),
            $now,
        );
    }

    /**
     * @param  EloquentCollection<int, AttendanceSession>  $sessions
     * @return array<string, mixed>
     */
    public function getDailySummaryFromSessions(Employee $employee, Carbon $date, EloquentCollection $sessions, bool $hasApprovedLeave, ?Carbon $now = null): array
    {
        $date = $this->localTime($date);

        $closedSessionSeconds = [];
        $currentlyWorking = false;
        $openSessionSeconds = null;

        foreach ($sessions as $session) {
            if ($session->isOpen()) {
                $currentlyWorking = true;
                $nowForCalc = $this->localTime($now ?? now());
                $openSessionSeconds = $this->calculator->calculateOpenSessionSeconds(
                    $session->check_in_at,
                    $nowForCalc,
                    $session->work_date,
                );
            } else {
                $closedSessionSeconds[] = $session->counted_seconds;
            }
        }

        $summary = $this->calculator->buildDailySummary(
            $closedSessionSeconds,
            $openSessionSeconds,
            $currentlyWorking,
            $sessions->count(),
        );

        $workState = $this->deriveWorkState($date, (int) $summary['worked_seconds'], $currentlyWorking, $hasApprovedLeave, $now);
        $summary['work_state'] = $workState;
        $summary['attendance_status'] = $this->deriveAttendanceStatus((int) $summary['worked_seconds'], $workState);

        return $summary;
    }

    public function recordManualSession(Employee $employee, Carbon $checkIn, Carbon $checkOut, User $admin, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($employee, $checkIn, $checkOut, $admin, $reason): AttendanceSession {
            if ($admin->role !== UserRole::Admin) {
                throw new \DomainException('Only admins can add manual attendance.');
            }

            if (trim($reason) === '') {
                throw new \DomainException('Please provide a reason for this manual attendance entry.');
            }

            $checkIn = $this->localTime($checkIn);
            $checkOut = $this->localTime($checkOut);

            if ($checkOut->lte($checkIn)) {
                throw new \DomainException('Check-out time must be after check-in time.');
            }

            $employee = Employee::query()->where('id', $employee->id)->lockForUpdate()->firstOrFail();

            $this->assertNoOverlap($employee, $checkIn, $checkOut);

            $workDate = $checkIn->copy()->startOfDay();

            return AttendanceSession::create([
                'employee_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'counted_seconds' => $this->calculator->calculateSessionSeconds($checkIn, $checkOut, $workDate),
                'source' => AttendanceSource::AdminManual,
                'created_by' => $admin->id,
                'note' => $reason,
            ]);
        });
    }

    public function getOpenSession(Employee $employee): ?AttendanceSession
    {
        return AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->whereNull('check_out_at')
            ->first();
    }

    private function localTime(Carbon $date): Carbon
    {
        return $date->copy()->timezone(config('app.timezone'));
    }

    private function hasApprovedLeave(Employee $employee, Carbon $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->exists();
    }

    private function deriveWorkState(Carbon $date, int $workedSeconds, bool $currentlyWorking, bool $hasApprovedLeave, ?Carbon $now = null): WorkState
    {
        if ($hasApprovedLeave) {
            return WorkState::OnLeave;
        }

        if ($currentlyWorking) {
            return WorkState::Working;
        }

        if ($workedSeconds > 0) {
            return WorkState::CheckedOut;
        }

        $now = $this->localTime($now ?? now());

        if ($date->copy()->startOfDay()->gt($now->copy()->startOfDay())) {
            return WorkState::NotCheckedInYet;
        }

        if ($date->isSameDay($now)) {
            $windowEnd = $date->copy()->startOfDay()->addSeconds($this->timeToSeconds($this->calculator->getWindowEnd()));

            return $now->lt($windowEnd) ? WorkState::NotCheckedInYet : WorkState::Absent;
        }

        return WorkState::Absent;
    }

    private function deriveAttendanceStatus(int $workedSeconds, WorkState $workState): ?AttendanceStatus
    {
        if ($workState === WorkState::OnLeave) {
            return AttendanceStatus::OnLeave;
        }

        if ($workState === WorkState::Absent) {
            return AttendanceStatus::Absent;
        }

        if ($workedSeconds === 0) {
            return null;
        }

        return $this->calculator->deriveStatus($workedSeconds);
    }

    private function assertNoOverlap(Employee $employee, Carbon $checkIn, Carbon $checkOut): void
    {
        $overlapExists = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->where('check_in_at', '<', $checkOut)
            ->where(function ($query) use ($checkIn): void {
                $query->whereNull('check_out_at')
                    ->orWhere('check_out_at', '>', $checkIn);
            })
            ->lockForUpdate()
            ->exists();

        if ($overlapExists) {
            throw new \DomainException('This attendance period overlaps an existing session.');
        }
    }

    private function timeToSeconds(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + ((int) ($parts[2] ?? 0));
    }
}
