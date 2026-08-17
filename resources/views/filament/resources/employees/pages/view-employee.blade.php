<x-filament-panels::page>
    @php
        $employee = $this->record;
        $summary = $this->getSummary();
    @endphp

    <div class="space-y-6">
        <div class="hr-card-pad bg-gradient-to-br from-[#151C2C] to-[#1B2436]">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#7C3AED] text-xl font-bold text-white shadow-[0_16px_34px_rgba(124,58,237,0.28)]">
                        {{ collect(explode(' ', $employee->user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                    </div>
                    <div>
                        <h2 class="text-3xl font-semibold tracking-tight text-[#F8FAFC]">{{ $employee->user->name }}</h2>
                        <p class="text-sm hr-muted">{{ $employee->employee_number }} · {{ $employee->department }} · {{ $employee->job_title }}</p>
                    </div>
                </div>
                <x-filament::badge color="{{ $employee->is_active ? 'success' : 'danger' }}">
                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                </x-filament::badge>
            </div>

            <dl class="mt-6 grid gap-4 md:grid-cols-4">
                <div><dt class="text-xs text-[#A7B0C0]">Email</dt><dd class="font-medium text-[#F8FAFC]">{{ $employee->user->email }}</dd></div>
                <div><dt class="text-xs text-[#A7B0C0]">Phone</dt><dd class="font-medium text-[#F8FAFC]">{{ $employee->phone ?? 'N/A' }}</dd></div>
                <div><dt class="text-xs text-[#A7B0C0]">Hire Date</dt><dd class="font-medium text-[#F8FAFC]">{{ $employee->hire_date?->format('d M Y') ?? 'N/A' }}</dd></div>
                <div><dt class="text-xs text-[#A7B0C0]">Current State</dt><dd class="font-medium text-[#F8FAFC]">{{ $summary['currently_working'] }}</dd></div>
            </dl>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="hr-kpi"><dt class="text-xs hr-muted">Worked Today</dt><dd class="mt-2 text-2xl font-semibold">{{ $summary['today_worked'] }}</dd></div>
            <div class="hr-kpi"><dt class="text-xs hr-muted">Today Status</dt><dd class="mt-2"><x-filament::badge color="{{ $summary['today_status_color'] }}">{{ $summary['today_status'] }}</x-filament::badge></dd></div>
            <div class="hr-kpi"><dt class="text-xs hr-muted">Month Hours</dt><dd class="mt-2 text-2xl font-semibold">{{ $summary['month_hours'] }}</dd></div>
            <div class="hr-kpi"><dt class="text-xs hr-muted">Leave Remaining</dt><dd class="mt-2 text-2xl font-semibold">{{ $summary['remaining_days'] }} / {{ $summary['annual_allowance'] }} days</dd></div>
        </div>

        <div class="hr-card-pad">
            <h3 class="hr-title">Leave Balance</h3>
            <dl class="grid gap-4 md:grid-cols-3">
                <div><dt class="text-xs text-[#A7B0C0]">Annual Allowance</dt><dd class="font-medium text-[#F8FAFC]">{{ $summary['annual_allowance'] }} days</dd></div>
                <div><dt class="text-xs text-[#A7B0C0]">Used Leave</dt><dd class="font-medium text-[#F8FAFC]">{{ $summary['used_days'] }} days</dd></div>
                <div><dt class="text-xs text-[#A7B0C0]">Remaining Leave</dt><dd class="font-medium text-[#F8FAFC]">{{ $summary['remaining_days'] }} days</dd></div>
            </dl>
        </div>

        <div class="hr-card-pad">
            <h3 class="hr-title">Attendance History</h3>

            <div class="space-y-4">
                @forelse ($this->getAttendanceDays() as $day)
                    <div class="rounded-2xl border border-[#293449] bg-[#1B2436] p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="font-semibold">{{ $day['date'] }}</h3>
                                <p class="text-sm text-[#A7B0C0]">Total: {{ $day['total'] }} · Required: {{ $day['required'] }} · Remaining: {{ $day['remaining'] }} · Extra: {{ $day['extra'] }}</p>
                            </div>
                            <x-filament::badge>{{ $day['status'] }}</x-filament::badge>
                        </div>

                        <div class="mt-3 overflow-x-auto rounded-md border border-gray-100 dark:border-gray-800">
                            <table class="w-full min-w-[42rem] divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left">Check In</th>
                                        <th class="px-3 py-2 text-left">Check Out</th>
                                        <th class="px-3 py-2 text-left">Counted Duration</th>
                                        <th class="px-3 py-2 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($day['sessions'] as $session)
                                        <tr>
                                            <td class="px-3 py-2">{{ $session->check_in_at->timezone(config('app.timezone'))->format('h:i A') }}</td>
                                            <td class="px-3 py-2">{{ $session->check_out_at?->timezone(config('app.timezone'))->format('h:i A') ?? 'Open' }}</td>
                                            <td class="px-3 py-2">{{ \App\Support\TimeFormatter::secondsToHumanReadable($session->counted_seconds) }}</td>
                                            <td class="px-3 py-2">{{ $session->isOpen() ? 'Open' : 'Closed' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[#A7B0C0]">No attendance history yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
