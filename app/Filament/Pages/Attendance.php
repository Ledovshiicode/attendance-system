<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkState;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Attendance extends Page
{
    protected string $view = 'filament.pages.attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Attendance';

    public ?string $date = null;

    public ?int $employeeId = null;

    public ?string $department = null;

    public ?string $status = null;

    public ?string $workState = null;

    public ?int $selectedEmployeeId = null;

    public ?int $manualEmployeeId = null;

    public ?string $manualDate = null;

    public ?string $manualCheckInTime = null;

    public ?string $manualCheckOutTime = null;

    public ?string $manualReason = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->role === UserRole::Admin;
    }

    public function mount(): void
    {
        $this->date ??= now()->timezone(config('app.timezone'))->toDateString();
        $this->manualDate ??= $this->date;
    }

    public function previousDay(): void
    {
        $this->date = $this->selectedDate()->subDay()->toDateString();
        $this->manualDate = $this->date;
    }

    public function nextDay(): void
    {
        $this->date = $this->selectedDate()->addDay()->toDateString();
        $this->manualDate = $this->date;
    }

    public function today(): void
    {
        $this->date = now()->timezone(config('app.timezone'))->toDateString();
        $this->manualDate = $this->date;
    }

    public function resetFilters(): void
    {
        $this->employeeId = null;
        $this->department = null;
        $this->status = null;
        $this->workState = null;
    }

    public function viewSessions(int $employeeId): void
    {
        $this->selectedEmployeeId = $employeeId;
    }

    public function closeSessions(): void
    {
        $this->selectedEmployeeId = null;
    }

    public function addManualAttendance(): void
    {
        try {
            if (! $this->manualEmployeeId || ! $this->manualDate || ! $this->manualCheckInTime || ! $this->manualCheckOutTime) {
                throw new \DomainException('Please complete all manual attendance fields.');
            }

            $employee = Employee::query()->findOrFail($this->manualEmployeeId);
            $timezone = config('app.timezone');
            $checkIn = Carbon::parse($this->manualDate.' '.$this->manualCheckInTime, $timezone);
            $checkOut = Carbon::parse($this->manualDate.' '.$this->manualCheckOutTime, $timezone);

            app(AttendanceService::class)->recordManualSession(
                $employee,
                $checkIn,
                $checkOut,
                Filament::auth()->user(),
                (string) $this->manualReason,
            );

            Notification::make()
                ->title('Manual attendance added successfully')
                ->success()
                ->send();

            $this->manualEmployeeId = null;
            $this->manualCheckInTime = null;
            $this->manualCheckOutTime = null;
            $this->manualReason = null;
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Manual attendance failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, string>
     */
    public function getEmployees(): array
    {
        return Employee::query()
            ->with('user')
            ->orderBy('employee_number')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [
                $employee->id => $employee->employee_number.' - '.$employee->user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getDepartments(): array
    {
        return Employee::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDailyRows(): array
    {
        $date = $this->selectedDate();

        /** @var AttendanceService $attendanceService */
        $attendanceService = app(AttendanceService::class);

        return Employee::query()
            ->with([
                'user',
                'attendanceSessions' => fn ($query) => $query
                    ->where('work_date', $date->toDateString())
                    ->with('creator')
                    ->orderBy('check_in_at'),
                'leaveRequests' => fn ($query) => $query
                    ->where('status', LeaveRequestStatus::Approved)
                    ->whereDate('from_date', '<=', $date->toDateString())
                    ->whereDate('to_date', '>=', $date->toDateString()),
            ])
            ->when($this->employeeId, fn ($query) => $query->whereKey($this->employeeId))
            ->when($this->department, fn ($query) => $query->where('department', $this->department))
            ->orderBy('employee_number')
            ->get()
            ->map(function (Employee $employee) use ($attendanceService, $date): array {
                /** @var EloquentCollection<int, AttendanceSession> $sessions */
                $sessions = $employee->attendanceSessions;
                $summary = $attendanceService->getDailySummaryFromSessions($employee, $date, $sessions, $employee->leaveRequests->isNotEmpty());
                $status = $summary['attendance_status'];
                $workState = $summary['work_state'];

                return [
                    'employee' => $employee,
                    'employee_name' => $employee->user->name,
                    'employee_number' => $employee->employee_number,
                    'department' => $employee->department,
                    'worked_seconds' => $summary['worked_seconds'],
                    'progress' => min(100, (int) floor(((int) $summary['worked_seconds'] / max((int) $summary['required_seconds'], 1)) * 100)),
                    'worked' => $summary['worked_seconds'] > 0 ? TimeFormatter::secondsToHumanReadable($summary['worked_seconds']) : '0m',
                    'remaining_extra' => $this->remainingExtraLabel((int) $summary['remaining_seconds'], (int) $summary['extra_seconds'], $workState),
                    'status' => $status,
                    'status_label' => $status instanceof AttendanceStatus ? $status->label() : 'Not Yet Recorded',
                    'status_color' => $status instanceof AttendanceStatus ? $status->color() : 'gray',
                    'work_state' => $workState,
                    'work_state_label' => $workState->label(),
                    'work_state_color' => $workState->color(),
                    'session_count' => $summary['session_count'],
                ];
            })
            ->filter(fn (array $row): bool => (! $this->status || $row['status']?->value === $this->status)
                && (! $this->workState || $row['work_state']->value === $this->workState))
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function getKpis(): array
    {
        $rows = collect($this->getDailyRows());

        return [
            'Working Now' => $rows->where('work_state', WorkState::Working)->count(),
            'Completed' => $rows->where('status', AttendanceStatus::Completed)->count(),
            'Below Required' => $rows->where('status', AttendanceStatus::BelowRequired)->count(),
            'Above Required' => $rows->where('status', AttendanceStatus::AboveRequired)->count(),
            'Not Checked In / Absent' => $rows->filter(fn (array $row): bool => in_array($row['work_state'], [WorkState::NotCheckedInYet, WorkState::Absent], true))->count(),
            'On Leave' => $rows->where('work_state', WorkState::OnLeave)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSelectedSessionDetails(): ?array
    {
        if (! $this->selectedEmployeeId) {
            return null;
        }

        $employee = Employee::query()->with('user')->find($this->selectedEmployeeId);

        if (! $employee) {
            return null;
        }

        $date = $this->selectedDate();
        $sessions = AttendanceSession::query()
            ->with('creator')
            ->where('employee_id', $employee->id)
            ->where('work_date', $date->toDateString())
            ->orderBy('check_in_at')
            ->get();
        $summary = app(AttendanceService::class)->getDailySummary($employee, $date);
        $calculator = app(AttendanceCalculator::class);

        return [
            'employee' => $employee,
            'date' => $date->format('d M Y'),
            'summary' => [
                'worked' => TimeFormatter::secondsToHumanReadable($summary['worked_seconds']),
                'required' => TimeFormatter::secondsToHumanReadable($summary['required_seconds']),
                'remaining' => TimeFormatter::secondsToHumanReadable($summary['remaining_seconds']),
                'extra' => TimeFormatter::secondsToHumanReadable($summary['extra_seconds']),
                'status' => $summary['attendance_status']?->label() ?? 'Not Yet Recorded',
            ],
            'sessions' => $sessions->map(fn (AttendanceSession $session): array => [
                'check_in' => $session->check_in_at->timezone(config('app.timezone'))->format('h:i A'),
                'check_out' => $session->check_out_at?->timezone(config('app.timezone'))->format('h:i A') ?? 'Now',
                'duration' => TimeFormatter::secondsToHumanReadable($session->isOpen()
                    ? $calculator->calculateOpenSessionSeconds($session->check_in_at, now()->timezone(config('app.timezone')), $session->work_date)
                    : $session->counted_seconds),
                'state' => $session->isOpen() ? 'Working' : 'Closed',
                'source' => $session->source->label(),
                'created_by' => $session->creator?->name,
                'note' => $session->note,
            ])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getAttendanceStatusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getWorkStateOptions(): array
    {
        return collect(WorkState::cases())
            ->mapWithKeys(fn (WorkState $state): array => [$state->value => $state->label()])
            ->all();
    }

    public function getManualDurationPreview(): string
    {
        if (! $this->manualDate || ! $this->manualCheckInTime || ! $this->manualCheckOutTime) {
            return 'Complete times to preview counted duration.';
        }

        $timezone = config('app.timezone');
        $checkIn = Carbon::parse($this->manualDate.' '.$this->manualCheckInTime, $timezone);
        $checkOut = Carbon::parse($this->manualDate.' '.$this->manualCheckOutTime, $timezone);

        if ($checkOut->lte($checkIn)) {
            return 'Check-out time must be after check-in time.';
        }

        return TimeFormatter::secondsToHumanReadable(app(AttendanceCalculator::class)->calculateSessionSeconds($checkIn, $checkOut, $checkIn->copy()->startOfDay()));
    }

    public function getSelectedDateDisplay(): string
    {
        return $this->selectedDate()->format('d M Y');
    }

    private function selectedDate(): Carbon
    {
        return Carbon::parse($this->date ?? now()->timezone(config('app.timezone'))->toDateString(), config('app.timezone'));
    }

    private function remainingExtraLabel(int $remainingSeconds, int $extraSeconds, WorkState $workState): string
    {
        if ($workState === WorkState::OnLeave || $workState === WorkState::NotCheckedInYet || $workState === WorkState::Absent) {
            return '—';
        }

        if ($extraSeconds > 0) {
            return TimeFormatter::secondsToHumanReadable($extraSeconds).' extra';
        }

        return $remainingSeconds > 0 ? TimeFormatter::secondsToHumanReadable($remainingSeconds).' remaining' : '—';
    }
}
