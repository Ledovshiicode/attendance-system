<x-filament-panels::page>
    @php
        $user = \Filament\Facades\Filament::auth()->user();
        $hour = now()->timezone(config('app.timezone'))->hour;
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    @endphp

    <div class="grid gap-6">
        <div class="hr-card-pad bg-gradient-to-br from-[#151C2C] via-[#151C2C] to-[#1B2436]">
            <p class="text-sm font-semibold text-[#C4B5FD]">{{ $greeting }}</p>
            <h2 class="mt-1 text-3xl font-semibold tracking-tight text-[#F8FAFC]">{{ $user?->name }}</h2>
            <p class="mt-2 hr-muted">Track today’s attendance, sessions, leave balance, and recent working hours.</p>
        </div>

        @livewire(\App\Filament\Employee\Widgets\AttendanceActionWidget::class)
        @livewire(\App\Filament\Employee\Widgets\TodayStatsWidget::class)

        <div class="grid gap-6 lg:grid-cols-2">
            @livewire(\App\Filament\Employee\Widgets\WeeklyHoursChart::class)
            @livewire(\App\Filament\Employee\Widgets\MonthlyHoursChart::class)
        </div>

        @livewire(\App\Filament\Employee\Widgets\TodaySessionsWidget::class)
        @livewire(\App\Filament\Employee\Widgets\LeaveSummaryWidget::class)
    </div>
</x-filament-panels::page>
