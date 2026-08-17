<?php

namespace App\Filament\Employee\Resources\LeaveRequestResource\Pages;

use App\Enums\LeaveRequestStatus;
use App\Filament\Employee\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = $this->record;

        if ($leaveRequest->employee_id !== Filament::auth()->user()?->employee?->id) {
            abort(403, 'You are not authorized to view this leave request.');
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Leave Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('leaveType.name')
                            ->label('Leave Type'),

                        Infolists\Components\TextEntry::make('from_date')
                            ->label('From Date')
                            ->date('d M Y'),

                        Infolists\Components\TextEntry::make('to_date')
                            ->label('To Date')
                            ->date('d M Y'),

                        Infolists\Components\TextEntry::make('total_days')
                            ->label('Total Days')
                            ->suffix(' day(s)'),

                        Infolists\Components\TextEntry::make('reason')
                            ->label('Reason'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (mixed $state): string => match ($state) {
                                LeaveRequestStatus::Pending => 'warning',
                                LeaveRequestStatus::Approved => 'success',
                                LeaveRequestStatus::Rejected => 'danger',
                            })
                            ->formatStateUsing(fn (mixed $state): string => $state->label()),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('N/A'),

                        Infolists\Components\TextEntry::make('attachment_path')
                            ->label('Attachment')
                            ->placeholder('No attachment uploaded')
                            ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : 'No attachment uploaded'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Submitted At')
                            ->dateTime('d M Y h:i A'),
                    ])
                    ->columns(2),
            ]);
    }
}
