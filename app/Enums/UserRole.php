<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Employee => 'Employee',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }
}
