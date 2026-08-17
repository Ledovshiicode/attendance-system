<?php

namespace App\Enums;

enum WorkState: string
{
    case Working = 'working';
    case CheckedOut = 'checked_out';
    case NotCheckedInYet = 'not_checked_in_yet';
    case Absent = 'absent';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Working',
            self::CheckedOut => 'Checked Out',
            self::NotCheckedInYet => 'Not Checked In Yet',
            self::Absent => 'Absent',
            self::OnLeave => 'On Leave',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Working => 'success',
            self::CheckedOut => 'gray',
            self::NotCheckedInYet => 'gray',
            self::Absent => 'danger',
            self::OnLeave => 'info',
        };
    }
}
