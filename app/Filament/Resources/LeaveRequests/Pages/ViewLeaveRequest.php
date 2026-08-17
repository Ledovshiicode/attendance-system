<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Enums\LeaveRequestStatus;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Tables\LeaveRequestsTable;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadAttachment')
                ->label('Download Attachment')
                ->visible(fn (): bool => filled($this->record->attachment_path))
                ->action(function () {
                    $path = (string) $this->record->attachment_path;

                    abort_unless(str_starts_with($path, 'leave-documents/'), 404);
                    abort_unless(Storage::disk('local')->exists($path), 404);

                    return Storage::disk('local')->download($path, basename($path));
                }),

            LeaveRequestsTable::approveAction()
                ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequestStatus::Pending),

            LeaveRequestsTable::rejectAction()
                ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequestStatus::Pending),
        ];
    }
}
