<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class LeaveStatisticsChart extends ChartWidget
{
    protected ?string $heading = 'Leave Request Status';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        return app(AdminDashboardService::class)->leaveStatusDistribution();
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
