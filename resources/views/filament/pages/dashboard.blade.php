<x-filament-panels::page>
    @php
        $overview = $this->getOverview();
        $statusCards = [
            ['label' => 'Total Employees', 'value' => $overview['total_active_employees'], 'caption' => 'Active headcount', 'icon' => 'heroicon-o-users', 'tone' => 'bg-[rgba(124,58,237,0.15)] text-[#C4B5FD]'],
            ['label' => 'Working Now', 'value' => $overview['currently_working'], 'caption' => 'Open sessions', 'icon' => 'heroicon-o-signal', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Completed', 'value' => $overview['completed_today'], 'caption' => 'Reached 7h', 'icon' => 'heroicon-o-check-badge', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Below Required', 'value' => $overview['below_required_today'], 'caption' => 'Still under target', 'icon' => 'heroicon-o-clock', 'tone' => 'bg-amber-50 text-amber-600'],
            ['label' => 'Above Required', 'value' => $overview['above_required_today'], 'caption' => 'Over target', 'icon' => 'heroicon-o-arrow-trending-up', 'tone' => 'bg-sky-50 text-sky-600'],
            ['label' => 'Pending Leaves', 'value' => $overview['pending_leave_requests'], 'caption' => 'Awaiting decision', 'icon' => 'heroicon-o-calendar-days', 'tone' => 'bg-amber-50 text-amber-600'],
        ];
    @endphp

    <div class="space-y-6">
        <div class="hr-card-pad overflow-hidden bg-gradient-to-br from-[#151C2C] via-[#151C2C] to-[#1B2436]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-[#C4B5FD]">{{ $this->getGreeting() }}</p>
                    <h2 class="mt-1 text-3xl font-semibold tracking-tight text-[#F8FAFC]">{{ $this->getUserName() }}</h2>
                    <p class="mt-2 max-w-2xl hr-muted">Welcome to your system admin workspace for attendance, workforce state, and leave activity on {{ now()->timezone(config('app.timezone'))->format('d M Y') }}.</p>
                </div>
                <div class="rounded-2xl bg-[#7C3AED] px-5 py-4 text-white shadow-[0_18px_40px_rgba(124,58,237,0.25)]">
                    <p class="text-sm text-white/75">Average working time</p>
                    <p class="mt-1 text-3xl font-semibold">{{ $overview['average_working_time_today'] }}</p>
                    <p class="text-xs text-white/70">Among employees with counted attendance</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($statusCards as $card)
                <div class="hr-kpi">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium hr-muted">{{ $card['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-[#F8FAFC]">{{ $card['value'] }}</p>
                            <p class="mt-1 text-xs hr-muted">{{ $card['caption'] }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['tone'] }}">
                            @svg($card['icon'], 'h-5 w-5')
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="xl:col-span-3">@livewire(\App\Filament\Widgets\WeeklyAttendanceChart::class)</div>
            <div class="xl:col-span-2">@livewire(\App\Filament\Widgets\AttendanceStatusDistributionChart::class)</div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @livewire(\App\Filament\Widgets\MonthlyAttendanceChart::class)
            @livewire(\App\Filament\Widgets\EmployeeWorkingHoursChart::class)
        </div>

        @livewire(\App\Filament\Widgets\TodayOperationsWidget::class)
        @livewire(\App\Filament\Widgets\PendingLeaveRequestsWidget::class)
    </div>
</x-filament-panels::page>
