<?php

namespace App\Filament\Employee\Widgets;

use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MonthlyHoursChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Working Hours';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return ['datasets' => [], 'labels' => []];
        }

        /** @var AttendanceService $service */
        $service = app(AttendanceService::class);

        $startOfMonth = now()->timezone(config('app.timezone'))->startOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $labels = [];
        $data = [];

        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $startOfMonth->copy()->addDays($i);
            $labels[] = $date->format('d');
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
