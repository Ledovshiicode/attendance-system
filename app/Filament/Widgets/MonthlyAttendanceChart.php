<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class MonthlyAttendanceChart extends ChartWidget
{
    protected ?string $heading = 'Current Month Company Working Hours';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $data = app(AdminDashboardService::class)->monthlyTotalWorkingHours();
        $data['datasets'][0]['backgroundColor'] = 'rgba(124, 58, 237, 0.15)';
        $data['datasets'][0]['borderColor'] = '#8B5CF6';
        $data['datasets'][0]['tension'] = 0.35;
        $data['datasets'][0]['fill'] = true;

        return $data;
    }

    protected function getType(): string
    {
        return 'line';
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
