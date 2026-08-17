<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Services\AdminDashboardService;
use Filament\Widgets\Widget;

class TodayOperationsWidget extends Widget
{
    protected string $view = 'filament.widgets.today-operations-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return app(AdminDashboardService::class)->todayWorkforceRows();
    }

    public function employeeUrl(int $employeeId): string
    {
        return EmployeeResource::getUrl('view', ['record' => $employeeId]);
    }
}
