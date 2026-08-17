<?php

namespace App\Filament\Employee\Widgets;

use App\Services\LeaveService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeaveSummaryWidget extends BaseWidget
{
    protected ?string $heading = 'Leave Summary';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return [];
        }

        /** @var LeaveService $service */
        $service = app(LeaveService::class);
        $summary = $service->getLeaveSummary($employee);

        return [
            Stat::make('Total Allowance', $summary['total_allowance'].' days'),

            Stat::make('Used Days', $summary['used_days'].' days')
                ->color($summary['used_days'] > 0 ? 'warning' : 'success'),

            Stat::make('Remaining', $summary['remaining_days'].' days')
                ->color($summary['remaining_days'] > 10 ? 'success' : 'warning'),

            Stat::make('Pending', $summary['pending_requests'].' request(s)')
                ->color($summary['pending_requests'] > 0 ? 'info' : 'gray'),
        ];
    }
}
