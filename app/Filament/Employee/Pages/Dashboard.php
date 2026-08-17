<?php

namespace App\Filament\Employee\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.employee.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            Widgets\StatsOverviewWidget::class.'-today',
            Widgets\AccountWidget::class,
        ];
    }
}
