<x-filament-widgets::widget>
    <div class="hr-card-pad">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="hr-title">Today's Workforce</h3>
                <p class="text-sm hr-muted">Operational status for active employees today.</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-[#293449]">
            <table class="w-full min-w-[64rem] divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-[#1B2436]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Current State</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Worked Today</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Remaining / Extra</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">Attendance Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->getRows() as $row)
                        <tr class="transition hover:bg-[rgba(124,58,237,0.06)]">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[rgba(124,58,237,0.15)] text-xs font-bold text-[#C4B5FD]">
                                        {{ collect(explode(' ', $row['employee_name']))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <a href="{{ $this->employeeUrl($row['employee_id']) }}" class="font-semibold text-[#F8FAFC] hover:text-[#C4B5FD]">
                                        {{ $row['employee_name'] }}
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $row['department'] ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <x-filament::badge color="{{ $row['state'] === 'Working' ? 'success' : ($row['state'] === 'Absent' ? 'danger' : ($row['state'] === 'On Leave' ? 'gray' : 'info')) }}">
                                    {{ $row['state'] }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-3">{{ $row['worked_today'] }}</td>
                            <td class="px-4 py-3">{{ $row['remaining_or_extra'] }}</td>
                            <td class="px-4 py-3">
                                <x-filament::badge color="{{ $row['attendance_status_value'] === 'completed' ? 'success' : ($row['attendance_status_value'] === 'above_required' ? 'info' : ($row['attendance_status_value'] === 'below_required' ? 'warning' : 'gray')) }}">
                                    {{ $row['attendance_status'] }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-[#A7B0C0]">No active employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-widgets::widget>
