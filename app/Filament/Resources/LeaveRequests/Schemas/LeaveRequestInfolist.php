<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Enums\LeaveRequestStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeaveRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Employee')
                    ->schema([
                        TextEntry::make('employee.user.name')->label('Name'),
                        TextEntry::make('employee.employee_number')->label('Employee #'),
                        TextEntry::make('employee.department')->label('Department'),
                        TextEntry::make('employee.job_title')->label('Job Title'),
                    ])
                    ->columns(2),

                Section::make('Leave')
                    ->schema([
                        TextEntry::make('leaveType.name')->label('Type'),
                        TextEntry::make('from_date')->date('d M Y'),
                        TextEntry::make('to_date')->date('d M Y'),
                        TextEntry::make('total_days')->label('Total Days')->suffix(' day(s)'),
                        TextEntry::make('reason')->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (LeaveRequestStatus $state): string => match ($state) {
                                LeaveRequestStatus::Pending => 'warning',
                                LeaveRequestStatus::Approved => 'success',
                                LeaveRequestStatus::Rejected => 'danger',
                            })
                            ->formatStateUsing(fn (LeaveRequestStatus $state): string => $state->label()),
                    ])
                    ->columns(2),

                Section::make('Document')
                    ->schema([
                        TextEntry::make('attachment_path')
                            ->label('Attachment')
                            ->placeholder('No attachment uploaded')
                            ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : 'No attachment uploaded'),
                    ]),

                Section::make('Decision')
                    ->schema([
                        TextEntry::make('approver.name')->label('Approved / Rejected By')->placeholder('N/A'),
                        TextEntry::make('approved_at')->label('Decision Time')->dateTime('d M Y h:i A')->placeholder('N/A'),
                        TextEntry::make('rejection_reason')->placeholder('N/A')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
