<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case Employee = 'employee';
    case AdminManual = 'admin_manual';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee Check-in',
            self::AdminManual => 'Manual Admin Entry',
        };
    }
}
