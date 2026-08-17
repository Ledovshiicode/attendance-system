<?php

namespace App\Filament\Widgets;

use App\Enums\LeaveRequestStatus;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingLeaveRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Pending Leave Requests';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LeaveRequest::query()
                    ->with(['employee.user', 'leaveType'])
                    ->where('status', LeaveRequestStatus::Pending)
                    ->latest()
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('employee.user.name')
                    ->label('Employee'),

                TextColumn::make('leaveType.name')
                    ->label('Type'),

                TextColumn::make('dates')
                    ->state(fn (LeaveRequest $record): string => $record->from_date->format('d M Y').' - '.$record->to_date->format('d M Y')),

                TextColumn::make('total_days')
                    ->label('Days'),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y h:i A'),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn (LeaveRequest $record): string => LeaveRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query);
    }
}
