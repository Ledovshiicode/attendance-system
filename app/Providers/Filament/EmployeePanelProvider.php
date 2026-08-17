<?php

namespace App\Providers\Filament;

use App\Filament\Employee\Pages\Dashboard;
use App\Http\Controllers\Filament\IsolatedPanelLogoutController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EmployeePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('employee')
            ->path('employee')
            ->brandName('Attendance HR')
            ->login()
            ->authGuard('employee')
            ->viteTheme('resources/css/filament/theme.css')
            ->colors([
                'primary' => [
                    50 => '#f5f0ff',
                    100 => '#f1eaff',
                    200 => '#ded0ff',
                    300 => '#bea6ff',
                    400 => '#9b77ff',
                    500 => '#8a63ff',
                    600 => '#6c2cff',
                    700 => '#5a20e8',
                    800 => '#4b1bc2',
                    900 => '#3d179d',
                    950 => '#240a6b',
                ],
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('@include("filament.partials.topbar-greeting")'),
            )
            ->discoverResources(in: app_path('Filament/Employee/Resources'), for: 'App\Filament\Employee\Resources')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\Filament\Employee\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Employee/Widgets'), for: 'App\Filament\Employee\Widgets')
            ->widgets([])
            ->authenticatedRoutes(function (): void {
                Route::post('/logout', IsolatedPanelLogoutController::class)
                    ->name('auth.isolated-logout');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
