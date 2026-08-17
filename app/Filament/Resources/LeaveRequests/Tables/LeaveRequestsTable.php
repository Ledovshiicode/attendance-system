<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable(),

                TextColumn::make('leaveType.name')
                    ->label('Leave Type')
                    ->searchable(),

                TextColumn::make('from_date')
                    ->label('From')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('to_date')
                    ->label('To')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('total_days')
                    ->label('Days')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (LeaveRequestStatus $state): string => match ($state) {
                        LeaveRequestStatus::Pending => 'warning',
                        LeaveRequestStatus::Approved => 'success',
                        LeaveRequestStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (LeaveRequestStatus $state): string => $state->label()),

                TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime('d M Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(LeaveRequestStatus::cases())->mapWithKeys(fn (LeaveRequestStatus $status): array => [$status->value => $status->label()])->all()),

                SelectFilter::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(fn (): array => LeaveType::query()->orderBy('name')->pluck('name', 'id')->all()),

                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee.user', 'name'),

                Filter::make('starts_from')
                    ->query(fn (Builder $query, array $data): Builder => filled($data['from'] ?? null) ? $query->whereDate('from_date', '>=', $data['from']) : $query)
                    ->schema([
                        DatePicker::make('from')->label('From date starts after'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::rejectAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (LeaveRequest $record): string => sprintf(
                'Employee: %s. Leave type: %s. Requested days: %d. Current annual remaining balance: %d days. Deducts balance: %s.',
                $record->employee->user->name,
                $record->leaveType->name,
                $record->total_days,
                $record->employee->leaveBalance?->remainingDays() ?? 35,
                $record->leaveType->deducts_annual_balance ? 'yes' : 'no',
            ))
            ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequestStatus::Pending)
            ->action(function (LeaveRequest $record): void {
                try {
                    app(LeaveService::class)->approve($record, Filament::auth()->user());

                    Notification::make()
                        ->title('Leave request approved')
                        ->success()
                        ->send();
                } catch (\DomainException $exception) {
                    Notification::make()
                        ->title('Approval failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Rejection reason')
                    ->rows(3),
            ])
            ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequestStatus::Pending)
            ->action(function (LeaveRequest $record, array $data): void {
                try {
                    app(LeaveService::class)->reject($record, Filament::auth()->user(), $data['rejection_reason'] ?? null);

                    Notification::make()
                        ->title('Leave request rejected')
                        ->success()
                        ->send();
                } catch (\DomainException $exception) {
                    Notification::make()
                        ->title('Rejection failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
