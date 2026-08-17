<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class AttendanceStatusDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Today Attendance Distribution';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $distribution = app(AdminDashboardService::class)->todayOverview()['distribution'];

        return [
            'datasets' => [[
                'label' => 'Employees',
                'data' => array_values($distribution),
                'backgroundColor' => ['#34d399', '#fbbf24', '#60a5fa', '#a78bfa', '#f87171', '#cbd5e1'],
                'borderColor' => '#151C2C',
                'borderWidth' => 4,
                'hoverOffset' => 8,
            ]],
            'labels' => array_keys($distribution),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'color' => '#A7B0C0',
                        'boxWidth' => 12,
                        'usePointStyle' => true,
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => '#1B2436',
                    'titleColor' => '#F8FAFC',
                    'bodyColor' => '#F8FAFC',
                    'borderColor' => '#293449',
                    'borderWidth' => 1,
                ],
            ],
            'cutout' => '68%',
        ];
    }
}
