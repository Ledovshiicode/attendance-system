<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceStatusDistributionChart;
use App\Filament\Widgets\EmployeeWorkingHoursChart;
use App\Filament\Widgets\LeaveStatisticsChart;
use App\Filament\Widgets\MonthlyAttendanceChart;
use App\Filament\Widgets\PendingLeaveRequestsWidget;
use App\Filament\Widgets\TodayOperationsWidget;
use App\Filament\Widgets\TopKpiStatsWidget;
use App\Filament\Widgets\WeeklyAttendanceChart;
use App\Services\AdminDashboardService;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            TopKpiStatsWidget::class,
            WeeklyAttendanceChart::class,
            AttendanceStatusDistributionChart::class,
            MonthlyAttendanceChart::class,
            EmployeeWorkingHoursChart::class,
            LeaveStatisticsChart::class,
            TodayOperationsWidget::class,
            PendingLeaveRequestsWidget::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        return app(AdminDashboardService::class)->todayOverview();
    }

    public function getGreeting(): string
    {
        $hour = now()->timezone(config('app.timezone'))->hour;

        return $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    }

    public function getUserName(): string
    {
        return Filament::auth()->user()?->name ?? 'Admin';
    }
}
