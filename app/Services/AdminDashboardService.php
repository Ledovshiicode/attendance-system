<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\TimeFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(
        private AttendanceCalculator $attendanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function todayOverview(?Carbon $date = null): array
    {
        $date = $this->localDate($date);
        $employees = $this->activeEmployees();
        $employeeIds = $employees->modelKeys();
        $sessionsByEmployee = $this->sessionsForDate($employeeIds, $date)->groupBy('employee_id');
        $approvedLeaveEmployeeIds = $this->approvedLeaveEmployeeIdsForDate($date);

        $classification = [
            'completed' => 0,
            'below_required' => 0,
            'above_required' => 0,
            'absent' => 0,
            'not_checked_in_yet' => 0,
            'on_leave' => 0,
        ];
        $attendedSeconds = [];

        foreach ($employees as $employee) {
            $sessions = $sessionsByEmployee->get($employee->id, collect());
            $isOnLeave = $approvedLeaveEmployeeIds->contains($employee->id);

            if ($isOnLeave) {
                $classification['on_leave']++;
            }

            if ($sessions->isEmpty()) {
                if (! $isOnLeave) {
                    $classification[$this->noSessionClassification($date) === 'Absent' ? 'absent' : 'not_checked_in_yet']++;
                }

                continue;
            }

            $summary = $this->summaryFromSessions($sessions, $date);

            if (! $isOnLeave) {
                if ($summary['worked_seconds'] === 0) {
                    $classification[$this->noSessionClassification($date) === 'Absent' ? 'absent' : 'not_checked_in_yet']++;

                    continue;
                }

                $attendedSeconds[] = $summary['worked_seconds'];

                match ($summary['status']) {
                    AttendanceStatus::Completed => $classification['completed']++,
                    AttendanceStatus::BelowRequired => $classification['below_required']++,
                    AttendanceStatus::AboveRequired => $classification['above_required']++,
                };
            }
        }

        $currentlyWorking = AttendanceSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('check_out_at')
            ->distinct('employee_id')
            ->count('employee_id');

        $averageSeconds = count($attendedSeconds) > 0
            ? (int) floor(array_sum($attendedSeconds) / count($attendedSeconds))
            : 0;

        return [
            'total_active_employees' => $employees->count(),
            'currently_working' => $currentlyWorking,
            'completed_today' => $classification['completed'],
            'below_required_today' => $classification['below_required'],
            'above_required_today' => $classification['above_required'],
            'absent_today' => $classification['absent'],
            'not_checked_in_yet_today' => $classification['not_checked_in_yet'],
            'currently_on_leave' => $classification['on_leave'],
            'average_working_seconds_today' => $averageSeconds,
            'average_working_time_today' => TimeFormatter::secondsToHumanReadable($averageSeconds),
            'pending_leave_requests' => LeaveRequest::query()
                ->where('status', LeaveRequestStatus::Pending)
                ->count(),
            'distribution' => [
                'Completed' => $classification['completed'],
                'Below Required' => $classification['below_required'],
                'Above Required' => $classification['above_required'],
                'Absent' => $classification['absent'],
                'Not Checked In Yet' => $classification['not_checked_in_yet'],
                'On Leave' => $classification['on_leave'],
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function weeklyAverageWorkingHours(?Carbon $date = null): array
    {
        $date = $this->localDate($date);
        $start = $date->copy()->startOfWeek();
        $end = $date->copy()->endOfWeek();

        return $this->dailyAverageHours($start, $end, 'D');
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function monthlyTotalWorkingHours(?Carbon $date = null): array
    {
        $date = $this->localDate($date);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $employeeIds = $this->activeEmployees()->modelKeys();
        $sessions = AttendanceSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (AttendanceSession $session): string => $session->work_date->toDateString());

        $labels = [];
        $data = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $labels[] = $day->format('d');
            $totalSeconds = $sessions->get($day->toDateString(), collect())
                ->groupBy('employee_id')
                ->sum(fn (Collection $employeeSessions): int => $this->summaryFromSessions($employeeSessions, $day)['worked_seconds']);
            $data[] = TimeFormatter::secondsToHours((int) $totalSeconds);
        }

        return [
            'datasets' => [[
                'label' => 'Total company hours',
                'data' => $data,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#d97706',
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function employeeWorkingHoursCurrentMonth(?Carbon $date = null, int $limit = 10): array
    {
        $date = $this->localDate($date);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $employees = $this->activeEmployees();
        $sessions = AttendanceSession::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $rows = $employees
            ->map(function (Employee $employee) use ($sessions): array {
                $seconds = $sessions->get($employee->id, collect())
                    ->groupBy(fn (AttendanceSession $session): string => $session->work_date->toDateString())
                    ->sum(fn (Collection $daySessions, string $date): int => $this->summaryFromSessions($daySessions, Carbon::parse($date))['worked_seconds']);

                return [
                    'name' => $employee->user->name,
                    'hours' => TimeFormatter::secondsToHours((int) $seconds),
                ];
            })
            ->sortByDesc('hours')
            ->take($limit)
            ->values();

        return [
            'datasets' => [[
                'label' => 'Current month hours',
                'data' => $rows->pluck('hours')->all(),
                'backgroundColor' => '#0ea5e9',
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function leaveStatusDistribution(): array
    {
        $counts = LeaveRequest::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'datasets' => [[
                'label' => 'Leave requests',
                'data' => [
                    (int) $counts->get(LeaveRequestStatus::Pending->value, 0),
                    (int) $counts->get(LeaveRequestStatus::Approved->value, 0),
                    (int) $counts->get(LeaveRequestStatus::Rejected->value, 0),
                ],
                'backgroundColor' => ['#f59e0b', '#22c55e', '#ef4444'],
            ]],
            'labels' => ['Pending', 'Approved', 'Rejected'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function todayWorkforceRows(?Carbon $date = null): array
    {
        $date = $this->localDate($date);
        $employees = $this->activeEmployees();
        $sessionsByEmployee = $this->sessionsForDate($employees->modelKeys(), $date)->groupBy('employee_id');
        $approvedLeaveEmployeeIds = $this->approvedLeaveEmployeeIdsForDate($date);

        return $employees
            ->map(function (Employee $employee) use ($date, $sessionsByEmployee, $approvedLeaveEmployeeIds): array {
                $sessions = $sessionsByEmployee->get($employee->id, collect());
                $isOnLeave = $approvedLeaveEmployeeIds->contains($employee->id);

                if ($isOnLeave) {
                    return $this->workforceRow($employee, 'On Leave', null);
                }

                if ($sessions->isEmpty()) {
                    return $this->workforceRow($employee, $this->noSessionClassification($date), null);
                }

                $summary = $this->summaryFromSessions($sessions, $date);

                if ($summary['worked_seconds'] === 0 && ! $summary['currently_working']) {
                    return $this->workforceRow($employee, $this->noSessionClassification($date), null);
                }

                return $this->workforceRow(
                    $employee,
                    $summary['currently_working'] ? 'Working' : 'Checked Out',
                    $summary,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, LeaveRequest>
     */
    public function pendingLeaveRequests(int $limit = 8): EloquentCollection
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'leaveType'])
            ->where('status', LeaveRequestStatus::Pending)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    private function dailyAverageHours(Carbon $start, Carbon $end, string $labelFormat): array
    {
        $employeeIds = $this->activeEmployees()->modelKeys();
        $sessions = AttendanceSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (AttendanceSession $session): string => $session->work_date->toDateString());

        $labels = [];
        $data = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $labels[] = $day->format($labelFormat);
            $seconds = $sessions->get($day->toDateString(), collect())
                ->groupBy('employee_id')
                ->map(fn (Collection $employeeSessions): int => $this->summaryFromSessions($employeeSessions, $day)['worked_seconds'])
                ->values();

            $data[] = $seconds->isEmpty()
                ? 0
                : TimeFormatter::secondsToHours((int) floor($seconds->sum() / $seconds->count()));
        }

        return [
            'datasets' => [[
                'label' => 'Average hours among attendees',
                'data' => $data,
                'backgroundColor' => '#14b8a6',
                'borderColor' => '#0f766e',
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array<string, mixed>
     */
    private function summaryFromSessions(Collection $sessions, Carbon $date): array
    {
        $openSession = $sessions->first(fn (AttendanceSession $session): bool => $session->isOpen());

        return $this->attendanceCalculator->buildDailySummary(
            $sessions
                ->reject(fn (AttendanceSession $session): bool => $session->isOpen())
                ->pluck('counted_seconds')
                ->map(fn (mixed $seconds): int => (int) $seconds)
                ->all(),
            $openSession instanceof AttendanceSession
                ? $this->attendanceCalculator->calculateOpenSessionSeconds($openSession->check_in_at, now()->timezone(config('app.timezone')), $date)
                : null,
            $openSession instanceof AttendanceSession,
            $sessions->count(),
        );
    }

    /**
     * @return EloquentCollection<int, Employee>
     */
    private function activeEmployees(): EloquentCollection
    {
        return Employee::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('employee_number')
            ->get();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return EloquentCollection<int, AttendanceSession>
     */
    private function sessionsForDate(array $employeeIds, Carbon $date): EloquentCollection
    {
        return AttendanceSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('work_date', $date->toDateString())
            ->orderBy('check_in_at')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function approvedLeaveEmployeeIdsForDate(Carbon $date): Collection
    {
        return LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->pluck('employee_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    private function workforceRow(Employee $employee, string $state, ?array $summary): array
    {
        $workedSeconds = (int) ($summary['worked_seconds'] ?? 0);
        $remainingSeconds = (int) ($summary['remaining_seconds'] ?? 0);
        $extraSeconds = (int) ($summary['extra_seconds'] ?? 0);
        $status = $summary['status'] ?? null;

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->user->name,
            'department' => $employee->department,
            'state' => $state,
            'worked_today' => $summary ? TimeFormatter::secondsToHumanReadable($workedSeconds) : '0m',
            'remaining_or_extra' => $summary
                ? ($extraSeconds > 0
                    ? '+'.TimeFormatter::secondsToHumanReadable($extraSeconds)
                    : TimeFormatter::secondsToHumanReadable($remainingSeconds).' remaining')
                : 'N/A',
            'attendance_status' => $status instanceof AttendanceStatus ? $status->label() : $state,
            'attendance_status_value' => $status instanceof AttendanceStatus ? $status->value : null,
        ];
    }

    private function localDate(?Carbon $date = null): Carbon
    {
        return ($date?->copy() ?? now())->timezone(config('app.timezone'));
    }

    private function noSessionClassification(Carbon $date): string
    {
        $windowEnd = $date->copy()->startOfDay()->addSeconds(
            $this->timeToSeconds($this->attendanceCalculator->getWindowEnd()),
        );

        $now = now()->timezone(config('app.timezone'));

        if ($date->isFuture() && ! $date->isSameDay($now)) {
            return 'Not Checked In Yet';
        }

        if ($date->isSameDay($now) && $now->lt($windowEnd)) {
            return 'Not Checked In Yet';
        }

        return 'Absent';
    }

    private function timeToSeconds(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + ((int) ($parts[2] ?? 0));
    }
}
