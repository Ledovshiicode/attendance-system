<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case BelowRequired = 'below_required';
    case Completed = 'completed';
    case AboveRequired = 'above_required';
    case OnLeave = 'on_leave';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::BelowRequired => 'Below Required',
            self::Completed => 'Completed',
            self::AboveRequired => 'Above Required',
            self::OnLeave => 'On Leave',
            self::Absent => 'Absent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BelowRequired => 'warning',
            self::Completed => 'success',
            self::AboveRequired => 'info',
            self::OnLeave => 'info',
            self::Absent => 'danger',
        };
    }
}
