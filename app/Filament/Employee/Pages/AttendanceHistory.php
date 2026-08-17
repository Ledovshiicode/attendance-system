<?php

namespace App\Filament\Employee\Pages;

use App\Models\AttendanceSession;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class AttendanceHistory extends Page
{
    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Attendance History';

    protected static ?string $slug = 'attendance';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clock';
    }

    protected string $view = 'filament.employee.pages.attendance-history';

    public array $days = [];

    public ?string $selectedDay = null;

    public array $selectedDaySessions = [];

    public function mount(): void
    {
        $this->loadDays();
    }

    public function loadDays(): void
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return;
        }

        /** @var AttendanceService $service */
        $service = app(AttendanceService::class);

        $this->days = [];

        for ($i = 0; $i < 30; $i++) {
            $date = now()->timezone(config('app.timezone'))->subDays($i);
            $summary = $service->getDailySummary($employee, $date);
            $calculator = app(AttendanceCalculator::class);

            $sessions = AttendanceSession::query()
                ->where('employee_id', $employee->id)
                ->where('work_date', $date->toDateString())
                ->orderBy('check_in_at')
                ->get()
                ->map(fn (AttendanceSession $s) => [
                    'check_in' => $s->check_in_at->timezone(config('app.timezone'))->format('h:i A'),
                    'check_out' => $s->check_out_at?->timezone(config('app.timezone'))->format('h:i A') ?? '—',
                    'duration' => TimeFormatter::secondsToHumanReadable($s->isOpen()
                        ? $calculator->calculateOpenSessionSeconds($s->check_in_at, now()->timezone(config('app.timezone')), $s->work_date)
                        : $s->counted_seconds),
                    'is_open' => $s->isOpen(),
                    'source' => $s->source->label(),
                ])
                ->toArray();

            $status = $summary['attendance_status'];

            $this->days[] = [
                'date' => $date->format('Y-m-d'),
                'date_display' => $date->format('d M Y'),
                'worked_seconds' => $summary['worked_seconds'],
                'worked_display' => TimeFormatter::secondsToHumanReadable($summary['worked_seconds']),
                'required_display' => TimeFormatter::secondsToHumanReadable($summary['required_seconds']),
                'remaining_display' => TimeFormatter::secondsToHumanReadable($summary['remaining_seconds']),
                'extra_display' => TimeFormatter::secondsToHumanReadable($summary['extra_seconds']),
                'status' => $status,
                'status_label' => $status?->label() ?? 'Not Yet Recorded',
                'status_color' => $status?->color() ?? 'gray',
                'work_state_label' => $summary['work_state']->label(),
                'work_state_color' => $summary['work_state']->color(),
                'session_count' => $summary['session_count'],
                'sessions' => $sessions,
            ];
        }
    }

    public function selectDay(string $date): void
    {
        $this->selectedDay = $date;

        $day = collect($this->days)->firstWhere('date', $date);
        $this->selectedDaySessions = $day['sessions'] ?? [];
    }

    public function deselectDay(): void
    {
        $this->selectedDay = null;
        $this->selectedDaySessions = [];
    }
}
