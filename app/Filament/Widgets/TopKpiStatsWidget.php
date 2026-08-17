<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TopKpiStatsWidget extends BaseWidget
{
    protected ?string $heading = 'Today at a Glance';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $overview = app(AdminDashboardService::class)->todayOverview();

        return [
            Stat::make('Total Employees', $overview['total_active_employees'])
                ->description('Active operational headcount')
                ->color('primary'),

            Stat::make('Currently Working', $overview['currently_working'])
                ->description('Open attendance sessions')
                ->color('success'),

            Stat::make('Completed 7 Hours Today', $overview['completed_today'])
                ->description('Exactly required time')
                ->color('success'),

            Stat::make('Below Required Today', $overview['below_required_today'])
                ->description('Attended but below target')
                ->color('warning'),

            Stat::make('Above Required Today', $overview['above_required_today'])
                ->description('Worked more than required')
                ->color('info'),

            Stat::make('Absent Today', $overview['absent_today'])
                ->description('Excludes approved leave')
                ->color('danger'),

            Stat::make('Currently On Leave', $overview['currently_on_leave'])
                ->description('Approved leave covering today')
                ->color('gray'),

            Stat::make('Average Working Time', $overview['average_working_time_today'])
                ->description('Among employees with attendance')
                ->color('primary'),

            Stat::make('Pending Leave Requests', $overview['pending_leave_requests'])
                ->description('Awaiting admin decision')
                ->color('warning'),
        ];
    }
}
