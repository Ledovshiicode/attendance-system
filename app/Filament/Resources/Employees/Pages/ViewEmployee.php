<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Support\TimeFormatter;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected string $view = 'filament.resources.employees.pages.view-employee';

    public function getSummary(): array
    {
        /** @var Employee $employee */
        $employee = $this->record;

        /** @var AttendanceService $attendanceService */
        $attendanceService = app(AttendanceService::class);

        /** @var LeaveService $leaveService */
        $leaveService = app(LeaveService::class);

        $today = $attendanceService->getDailySummary($employee, now()->timezone(config('app.timezone')));
        $leave = $leaveService->getBalance($employee);

        $monthSeconds = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [now()->timezone(config('app.timezone'))->startOfMonth(), now()->timezone(config('app.timezone'))->endOfMonth()])
            ->sum('counted_seconds');

        return [
            'today_worked' => TimeFormatter::secondsToHumanReadable($today['worked_seconds']),
            'today_status' => $today['attendance_status']?->label() ?? 'Not Yet Recorded',
            'today_status_color' => $today['attendance_status']?->color() ?? 'gray',
            'currently_working' => $attendanceService->getOpenSession($employee) !== null ? 'Working' : 'Not working',
            'month_hours' => TimeFormatter::secondsToHumanReadable((int) $monthSeconds),
            'annual_allowance' => $leave['annual_allowance'],
            'used_days' => $leave['used_days'],
            'remaining_days' => $leave['remaining_days'],
        ];
    }

    public function getAttendanceDays(): array
    {
        /** @var Employee $employee */
        $employee = $this->record;

        /** @var AttendanceService $attendanceService */
        $attendanceService = app(AttendanceService::class);

        return collect(range(0, 29))
            ->map(function (int $offset) use ($employee, $attendanceService): array {
                $date = now()->timezone(config('app.timezone'))->subDays($offset);
                $summary = $attendanceService->getDailySummary($employee, $date);

                return [
                    'date' => $date->format('d M Y'),
                    'total' => TimeFormatter::secondsToHumanReadable($summary['worked_seconds']),
                    'required' => TimeFormatter::secondsToHumanReadable($summary['required_seconds']),
                    'remaining' => TimeFormatter::secondsToHumanReadable($summary['remaining_seconds']),
                    'extra' => TimeFormatter::secondsToHumanReadable($summary['extra_seconds']),
                    'status' => $summary['attendance_status']?->label() ?? 'Not Yet Recorded',
                    'sessions_count' => $summary['session_count'],
                    'sessions' => AttendanceSession::query()
                        ->where('employee_id', $employee->id)
                        ->where('work_date', $date->toDateString())
                        ->orderBy('check_in_at')
                        ->get(),
                ];
            })
            ->filter(fn (array $day): bool => $day['sessions_count'] > 0)
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
