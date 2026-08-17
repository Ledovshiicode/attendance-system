<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Annual',
                'deducts_annual_balance' => true,
                'requires_attachment' => false,
            ],
            [
                'name' => 'Sick',
                'deducts_annual_balance' => false,
                'requires_attachment' => true,
            ],
            [
                'name' => 'Emergency',
                'deducts_annual_balance' => false,
                'requires_attachment' => false,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
