<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class EmployeeWorkingHoursChart extends ChartWidget
{
    protected ?string $heading = 'Top Employee Working Hours This Month';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $data = app(AdminDashboardService::class)->employeeWorkingHoursCurrentMonth();
        $data['datasets'][0]['backgroundColor'] = '#8B5CF6';
        $data['datasets'][0]['borderRadius'] = 10;

        return $data;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['labels' => ['color' => '#A7B0C0']],
                'tooltip' => [
                    'backgroundColor' => '#1B2436',
                    'titleColor' => '#F8FAFC',
                    'bodyColor' => '#F8FAFC',
                    'borderColor' => '#293449',
                    'borderWidth' => 1,
                ],
            ],
            'scales' => [
                'x' => ['ticks' => ['color' => '#8E99AA'], 'grid' => ['color' => 'rgba(255,255,255,.07)']],
                'y' => ['ticks' => ['color' => '#8E99AA'], 'grid' => ['color' => 'rgba(255,255,255,.07)']],
            ],
        ];
    }
}
