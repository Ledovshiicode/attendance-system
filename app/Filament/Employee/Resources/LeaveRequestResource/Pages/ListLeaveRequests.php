<?php

namespace App\Filament\Employee\Resources\LeaveRequestResource\Pages;

use App\Filament\Employee\Resources\LeaveRequestResource;
use App\Filament\Employee\Widgets\LeaveSummaryWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Request Leave'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LeaveSummaryWidget::class,
        ];
    }
}
