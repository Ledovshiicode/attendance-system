<?php

namespace App\Filament\Employee\Widgets;

use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class AttendanceActionWidget extends Widget
{
    protected string $view = 'filament.employee.widgets.attendance-action';

    protected int|string|array $columnSpan = 'full';

    public ?string $openSessionCheckIn = null;

    public bool $isWorking = false;

    public string $workedToday = '0m';

    public string $statusLabel = 'Not Yet Recorded';

    public int $progress = 0;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $employee = Filament::auth()->user()?->employee;

        if (! $employee) {
            return;
        }

        /** @var AttendanceService $service */
        $service = app(AttendanceService::class);
        $openSession = $service->getOpenSession($employee);

        $this->isWorking = $openSession !== null;
        $this->openSessionCheckIn = $openSession?->check_in_at?->timezone(config('app.timezone'))->format('h:i A');

        $summary = $service->getDailySummary($employee, now()->timezone(config('app.timezone')));
        $this->workedToday = TimeFormatter::secondsToHumanReadable($summary['worked_seconds']);
        $this->statusLabel = $summary['attendance_status']?->label() ?? 'Not Yet Recorded';
        $this->progress = min(100, (int) floor(((int) $summary['worked_seconds'] / max((int) $summary['required_seconds'], 1)) * 100));
    }

    public function checkIn(): void
    {
        try {
            $employee = Filament::auth()->user()?->employee;
            /** @var AttendanceService $service */
            $service = app(AttendanceService::class);
            $service->checkIn($employee);

            Notification::make()
                ->title('Checked in successfully.')
                ->success()
                ->send();

            $this->refreshStatus();
            $this->dispatch('refreshDashboard');
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Check-in failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function checkOut(): void
    {
        try {
            $employee = Filament::auth()->user()?->employee;
            /** @var AttendanceService $service */
            $service = app(AttendanceService::class);
            $session = $service->checkOut($employee);

            Notification::make()
                ->title('Checked out successfully')
                ->body('Session counted: '.TimeFormatter::secondsToHumanReadable($session->counted_seconds))
                ->success()
                ->send();

            $this->refreshStatus();
            $this->dispatch('refreshDashboard');
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Check-out failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
