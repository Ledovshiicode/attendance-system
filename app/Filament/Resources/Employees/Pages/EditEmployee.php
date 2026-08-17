<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Employee $employee */
        $employee = $this->record->loadMissing('user');

        return [
            ...$data,
            'name' => $employee->user->name,
            'email' => $employee->user->email,
            'password' => null,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Employee $record */
        DB::transaction(function () use ($record, $data): void {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (filled($data['password'] ?? null)) {
                $userData['password'] = $data['password'];
            }

            $record->user->update($userData);

            $record->update([
                'employee_number' => $data['employee_number'],
                'department' => $data['department'],
                'job_title' => $data['job_title'],
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);
        });

        return $record->fresh(['user', 'leaveBalance']);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
