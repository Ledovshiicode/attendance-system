<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;

class IsolatedPanelLogoutController extends Controller
{
    public function __invoke(): LogoutResponse
    {
        Filament::auth()->logout();

        session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
