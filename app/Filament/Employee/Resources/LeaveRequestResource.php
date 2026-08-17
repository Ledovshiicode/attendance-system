<?php

namespace App\Filament\Employee\Resources;

use App\Enums\LeaveRequestStatus;
use App\Filament\Employee\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationLabel = 'Leave Requests';

    protected static ?string $modelLabel = 'Leave Request';

    protected static ?string $modelLabelPlural = 'Leave Requests';

    protected static ?string $slug = 'leave-requests';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(LeaveType::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $leaveType = LeaveType::find($state);
                        $set('requires_attachment', $leaveType?->requires_attachment ?? false);
                    }),

                Forms\Components\Hidden::make('requires_attachment')
                    ->default(false),

                Forms\Components\DatePicker::make('from_date')
                    ->label('From Date')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                        static::updateTotalDays($set, $state, $get('to_date'));
                    }),

                Forms\Components\DatePicker::make('to_date')
                    ->label('To Date')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                        static::updateTotalDays($set, $get('from_date'), $state);
                    }),

                Forms\Components\TextInput::make('total_days')
                    ->label('Total Days')
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),

                Forms\Components\FileUpload::make('attachment_path')
                    ->label('Attachment')
                    ->disk('local')
                    ->directory('leave-documents')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'])
                    ->rules(['mimes:pdf,jpg,jpeg,png', 'max:5120'])
                    ->maxSize(5120)
                    ->required(fn (Get $get): bool => (bool) $get('requires_attachment'))
                    ->visible(fn (Get $get): bool => (bool) $get('requires_attachment')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('employee_id', Filament::auth()->user()?->employee?->id ?? 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('Leave')
                    ->description(fn (LeaveRequest $record): string => $record->from_date->format('d M').' - '.$record->to_date->format('d M Y').' · '.$record->total_days.' days')
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_date')
                    ->label('From')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_date')
                    ->label('To')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_days')
                    ->label('Days')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state) {
                        LeaveRequestStatus::Pending => 'warning',
                        LeaveRequestStatus::Approved => 'success',
                        LeaveRequestStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (mixed $state): string => $state->label()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y h:i A')
                    ->sortable(),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canView(Model $record): bool
    {
        return $record->employee_id === Filament::auth()->user()?->employee?->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'view' => Pages\ViewLeaveRequest::route('/{record}'),
        ];
    }

    private static function updateTotalDays(Set $set, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate && $toDate) {
            $from = Carbon::parse($fromDate);
            $to = Carbon::parse($toDate);
            $set('total_days', $from->diffInDays($to) + 1);
        }
    }
}
