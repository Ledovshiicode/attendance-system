<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'annual_allowance',
        'used_days',
    ];

    protected function casts(): array
    {
        return [
            'annual_allowance' => 'integer',
            'used_days' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function remainingDays(): int
    {
        return $this->annual_allowance - $this->used_days;
    }
}
