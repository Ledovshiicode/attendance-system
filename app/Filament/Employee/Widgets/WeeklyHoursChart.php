<?php

namespace App\Filament\Employee\Widgets;

use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class WeeklyHoursChart extends ChartWidget
{
    protected ?string $heading = 'Weekly Working Hours';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return ['datasets' => [], 'labels' => []];
        }

        /** @var AttendanceService $service */
        $service = app(AttendanceService::class);

        $startOfWeek = now()->timezone(config('app.timezone'))->startOfWeek();
        $labels = [];
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $labels[] = $date->format('D');
            $summary = $service->getDailySummary($employee, $date);
            $data[] = TimeFormatter::secondsToHours($summary['worked_seconds']);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Working Hours',
                    'data' => $data,
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
