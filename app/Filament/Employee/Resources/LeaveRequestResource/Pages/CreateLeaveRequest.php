<?php

namespace App\Filament\Employee\Resources\LeaveRequestResource\Pages;

use App\Filament\Employee\Resources\LeaveRequestResource;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Filament::auth()->user()?->employee;
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        /** @var LeaveService $service */
        $service = app(LeaveService::class);

        $request = $service->submitRequest(
            employee: $employee,
            leaveType: $leaveType,
            fromDate: Carbon::parse($data['from_date']),
            toDate: Carbon::parse($data['to_date']),
            reason: $data['reason'],
            attachmentPath: $data['attachment_path'] ?? null,
        );

        Notification::make()
            ->title('Leave request submitted')
            ->success()
            ->send();

        return $request;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
