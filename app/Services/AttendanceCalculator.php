<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use Carbon\Carbon;

class AttendanceCalculator
{
    public function getRequiredSeconds(): int
    {
        return (int) config('attendance.required_seconds', 25200);
    }

    public function getWindowStart(): string
    {
        return config('attendance.window_start', '05:00:00');
    }

    public function getWindowEnd(): string
    {
        return config('attendance.window_end', '21:00:00');
    }

    /**
     * Calculate counted seconds for a single session within the attendance window.
     *
     * effective_start = max(check_in_at, work_date 05:00)
     * effective_end   = min(check_out_at, work_date 21:00)
     * counted_seconds = max(effective_end - effective_start, 0)
     */
    public function calculateSessionSeconds(Carbon $checkInAt, Carbon $checkOutAt, Carbon $workDate): int
    {
        $timezone = config('app.timezone');
        $checkInAt = $checkInAt->copy()->timezone($timezone);
        $checkOutAt = $checkOutAt->copy()->timezone($timezone);
        $workDate = $workDate->copy()->timezone($timezone);
        $windowStart = $this->getWindowStart();
        $windowEnd = $this->getWindowEnd();

        $dayStart = $workDate->copy()->startOfDay();

        $effectiveStart = $dayStart->copy()->addSeconds(
            $this->timeToSeconds($windowStart),
        );

        $effectiveEnd = $dayStart->copy()->addSeconds(
            $this->timeToSeconds($windowEnd),
        );

        $start = $checkInAt->gt($effectiveStart) ? $checkInAt : $effectiveStart;
        $end = $checkOutAt->lt($effectiveEnd) ? $checkOutAt : $effectiveEnd;

        if ($end->lte($start)) {
            return 0;
        }

        return max((int) $start->diffInSeconds($end), 0);
    }

    /**
     * Calculate counted seconds for an open session up to the given "now" time.
     */
    public function calculateOpenSessionSeconds(Carbon $checkInAt, Carbon $now, Carbon $workDate): int
    {
        return $this->calculateSessionSeconds($checkInAt, $now, $workDate);
    }

    public function deriveStatus(int $workedSeconds): AttendanceStatus
    {
        $required = $this->getRequiredSeconds();

        return match (true) {
            $workedSeconds < $required => AttendanceStatus::BelowRequired,
            $workedSeconds === $required => AttendanceStatus::Completed,
            default => AttendanceStatus::AboveRequired,
        };
    }

    public function calculateRemainingSeconds(int $workedSeconds): int
    {
        return max($this->getRequiredSeconds() - $workedSeconds, 0);
    }

    public function calculateExtraSeconds(int $workedSeconds): int
    {
        return max($workedSeconds - $this->getRequiredSeconds(), 0);
    }

    /**
     * Build a daily summary from an array of session durations (in seconds).
     *
     * @param  array<int, int>  $closedSessionSeconds  Seconds for each closed session
     * @param  int|null  $openSessionSeconds  Dynamically calculated seconds for open session
     * @param  bool  $currentlyWorking  Whether an open session exists
     * @param  int  $sessionCount  Total session count (closed + open)
     */
    public function buildDailySummary(
        array $closedSessionSeconds,
        ?int $openSessionSeconds,
        bool $currentlyWorking,
        int $sessionCount,
    ): array {
        $workedSeconds = array_sum($closedSessionSeconds) + ($openSessionSeconds ?? 0);
        $requiredSeconds = $this->getRequiredSeconds();

        return [
            'worked_seconds' => $workedSeconds,
            'required_seconds' => $requiredSeconds,
            'remaining_seconds' => $this->calculateRemainingSeconds($workedSeconds),
            'extra_seconds' => $this->calculateExtraSeconds($workedSeconds),
            'status' => $this->deriveStatus($workedSeconds),
            'session_count' => $sessionCount,
            'currently_working' => $currentlyWorking,
        ];
    }

    private function timeToSeconds(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + ((int) ($parts[2] ?? 0));
    }
}
