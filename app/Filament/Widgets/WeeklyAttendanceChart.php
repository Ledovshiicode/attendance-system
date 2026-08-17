<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class WeeklyAttendanceChart extends ChartWidget
{
    protected ?string $heading = 'Weekly Average Working Hours';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $data = app(AdminDashboardService::class)->weeklyAverageWorkingHours();
        $data['datasets'][0]['backgroundColor'] = '#7C3AED';
        $data['datasets'][0]['borderColor'] = '#8B5CF6';
        $data['datasets'][0]['borderRadius'] = 10;

        return $data;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return $this->darkChartOptions();
    }

    private function darkChartOptions(): array
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
