<?php

namespace App\Filament\Employee\Widgets;

use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return [];
        }

        /** @var AttendanceService $attendanceService */
        $attendanceService = app(AttendanceService::class);

        $summary = $attendanceService->getDailySummary($employee, now()->timezone(config('app.timezone')));

        /** @var LeaveService $leaveService */
        $leaveService = app(LeaveService::class);

        $leaveSummary = $leaveService->getLeaveSummary($employee);

        $status = $summary['attendance_status'];

        return [
            Stat::make('Worked Today', TimeFormatter::secondsToHumanReadable($summary['worked_seconds']))
                ->description('of '.TimeFormatter::secondsToHumanReadable($summary['required_seconds']).' required')
                ->descriptionIcon('heroicon-o-clock')
                ->color('primary'),

            Stat::make('Remaining Today', TimeFormatter::secondsToHumanReadable($summary['remaining_seconds']))
                ->description($summary['remaining_seconds'] > 0 ? 'time left to reach target' : 'target reached')
                ->descriptionIcon('heroicon-o-arrow-down')
                ->color($summary['remaining_seconds'] > 0 ? 'warning' : 'success'),

            Stat::make('Attendance Status', $status?->label() ?? 'Not Yet Recorded')
                ->description($summary['session_count'].' session(s) today')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color($status?->color() ?? 'gray'),

            Stat::make('Current State', $summary['work_state']->label())
                ->description('Application timezone: '.config('app.timezone'))
                ->descriptionIcon('heroicon-o-signal')
                ->color($summary['work_state']->color()),

            Stat::make('Annual Leave Balance', $leaveSummary['remaining_days'].' / '.$leaveSummary['total_allowance'].' days')
                ->description($leaveSummary['pending_requests'].' pending request(s)')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),
        ];
    }
}
