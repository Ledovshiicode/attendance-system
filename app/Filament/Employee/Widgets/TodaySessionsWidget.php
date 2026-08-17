<?php

namespace App\Filament\Employee\Widgets;

use App\Models\AttendanceSession;
use App\Services\AttendanceCalculator;
use App\Support\TimeFormatter;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TodaySessionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Today\'s Sessions';

    public function table(Table $table): Table
    {
        $employee = Filament::auth()->user()?->employee;

        return $table
            ->query(
                AttendanceSession::query()
                    ->where('employee_id', $employee?->id ?? 0)
                    ->where('work_date', now()->timezone(config('app.timezone'))->toDateString())
                    ->orderBy('check_in_at'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('check_in_at')
                    ->label('Check In')
                    ->dateTime('h:i A', timezone: config('app.timezone'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_at')
                    ->label('Check Out')
                    ->dateTime('h:i A', timezone: config('app.timezone'))
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('counted_display')
                    ->label('Duration')
                    ->state(function (AttendanceSession $record): string {
                        if ($record->isOpen()) {
                            $seconds = app(AttendanceCalculator::class)->calculateOpenSessionSeconds(
                                $record->check_in_at,
                                now()->timezone(config('app.timezone')),
                                $record->work_date,
                            );

                            return TimeFormatter::secondsToHumanReadable($seconds);
                        }

                        return TimeFormatter::secondsToHumanReadable($record->counted_seconds);
                    }),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (AttendanceSession $record): string => $record->source->label()),

                Tables\Columns\TextColumn::make('status_display')
                    ->label('Status')
                    ->state(fn (AttendanceSession $record): string => $record->isOpen() ? 'Working' : 'Closed')
                    ->badge()
                    ->color(fn (AttendanceSession $record): string => $record->isOpen() ? 'info' : 'gray'),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5]);
    }
}
