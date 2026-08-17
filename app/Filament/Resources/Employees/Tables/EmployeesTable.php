<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\TimeFormatter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_identity')
                    ->label('Employee')
                    ->state(function (Employee $record): HtmlString {
                        $initials = collect(explode(' ', $record->user->name))->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode('');

                        return new HtmlString(sprintf(
                            '<div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-[rgba(124,58,237,0.15)] text-sm font-bold text-[#C4B5FD]">%s</div><div><div class="font-semibold text-[#F8FAFC]">%s</div><div class="text-xs text-[#A7B0C0]">%s · %s</div></div></div>',
                            e($initials),
                            e($record->user->name),
                            e($record->employee_number),
                            e($record->department ?? 'N/A'),
                        ));
                    })
                    ->html()
                    ->searchable(['employee_number'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('employee_number', $direction)),

                TextColumn::make('employee_number')
                    ->label('Employee #')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('department')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('job_title')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('currently_working')
                    ->label('Working')
                    ->state(fn (Employee $record): string => $record->attendanceSessions()
                        ->whereNull('check_out_at')
                        ->exists() ? 'Working' : 'Not working')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Working' ? 'success' : 'gray'),

                TextColumn::make('today_worked')
                    ->label("Today's Working Time")
                    ->state(fn (Employee $record): string => TimeFormatter::secondsToHumanReadable(
                        app(AttendanceService::class)->getDailySummary($record, now()->timezone(config('app.timezone')))['worked_seconds'],
                    )),

                TextColumn::make('today_status')
                    ->label("Today's Status")
                    ->state(fn (Employee $record): ?AttendanceStatus => app(AttendanceService::class)->getDailySummary($record, now()->timezone(config('app.timezone')))['attendance_status'])
                    ->formatStateUsing(fn (?AttendanceStatus $state): string => $state?->label() ?? 'Not Yet Recorded')
                    ->badge()
                    ->color(fn (?AttendanceStatus $state): string => $state?->color() ?? 'gray'),

                TextColumn::make('leave_remaining')
                    ->label('Leave Remaining')
                    ->state(fn (Employee $record): string => ($record->leaveBalance?->remainingDays() ?? 35).' days'),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),

                Filter::make('inactive')
                    ->label('Inactive')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false)),

                SelectFilter::make('department')
                    ->options(fn (): array => Employee::query()
                        ->whereNotNull('department')
                        ->distinct()
                        ->orderBy('department')
                        ->pluck('department', 'department')
                        ->all()),

                SelectFilter::make('currently_working')
                    ->label('Currently Working')
                    ->options([
                        'yes' => 'Working now',
                        'no' => 'Not working',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'yes' => $query->whereHas('attendanceSessions', fn (Builder $query): Builder => $query->whereNull('check_out_at')),
                            'no' => $query->whereDoesntHave('attendanceSessions', fn (Builder $query): Builder => $query->whereNull('check_out_at')),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Employee $record): bool => $record->is_active)
                    ->action(fn (Employee $record) => $record->update(['is_active' => false])),

                Action::make('activate')
                    ->label('Activate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Employee $record): bool => ! $record->is_active)
                    ->action(fn (Employee $record) => $record->update(['is_active' => true])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('employee_number');
    }
}
