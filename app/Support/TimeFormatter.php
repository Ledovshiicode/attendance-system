<?php

namespace App\Support;

class TimeFormatter
{
    public static function secondsToHumanReadable(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }

        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    public static function secondsToHours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }
}
