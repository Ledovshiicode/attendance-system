<x-filament-panels::page>
    <div class="space-y-6">
        <div class="hr-card-pad flex flex-col gap-3 bg-gradient-to-br from-[#151C2C] to-[#1B2436] lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold tracking-tight text-[#F8FAFC]">Attendance</h2>
                <p class="mt-1 text-sm hr-muted">Monitor daily attendance, working hours and employee sessions.</p>
                <p class="mt-3 inline-flex rounded-full bg-[rgba(124,58,237,0.15)] px-3 py-1 text-sm font-semibold text-[#C4B5FD] shadow-sm">{{ $this->getSelectedDateDisplay() }}</p>
            </div>

            <x-filament::modal width="2xl">
                <x-slot name="trigger">
                    <x-filament::button icon="heroicon-m-plus">Add Manual Attendance</x-filament::button>
                </x-slot>

                <x-slot name="heading">Add Manual Attendance</x-slot>

                <div class="space-y-4">
                    <p class="rounded-xl bg-[rgba(124,58,237,0.15)] px-3 py-2 text-sm text-[#C4B5FD]">Only time between 05:00 AM and 09:00 PM counts toward working hours.</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1 text-sm font-medium">
                            <span>Employee</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="manualEmployeeId">
                                    <option value="">Select employee</option>
                                    @foreach ($this->getEmployees() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>

                        <label class="space-y-1 text-sm font-medium">
                            <span>Date</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="date" wire:model.live="manualDate" />
                            </x-filament::input.wrapper>
                        </label>

                        <label class="space-y-1 text-sm font-medium">
                            <span>Check In Time</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model.live="manualCheckInTime" />
                            </x-filament::input.wrapper>
                        </label>

                        <label class="space-y-1 text-sm font-medium">
                            <span>Check Out Time</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model.live="manualCheckOutTime" />
                            </x-filament::input.wrapper>
                        </label>
                    </div>

                    <label class="space-y-1 text-sm font-medium">
                        <span>Reason</span>
                        <x-filament::input.wrapper>
                            <textarea wire:model.live="manualReason" rows="3" class="block w-full border-0 bg-transparent px-3 py-2 text-sm outline-none"></textarea>
                        </x-filament::input.wrapper>
                    </label>

                    <div class="rounded-xl border border-[#293449] bg-[#202A3D] px-4 py-3 text-sm">
                        <span class="hr-muted">Counted Time</span>
                        <strong class="ml-2 text-[#F8FAFC]">{{ $this->getManualDurationPreview() }}</strong>
                    </div>

                    <div class="flex justify-end">
                        <x-filament::button wire:click="addManualAttendance" wire:loading.attr="disabled">Add Attendance</x-filament::button>
                    </div>
                </div>
            </x-filament::modal>
        </div>

        <div class="hr-filter-bar flex flex-wrap items-center gap-2">
            <x-filament::button color="gray" wire:click="previousDay">Previous Day</x-filament::button>
            <x-filament::input.wrapper class="w-44">
                <x-filament::input type="date" wire:model.live="date" />
            </x-filament::input.wrapper>
            <x-filament::button color="gray" wire:click="today">Today</x-filament::button>
            <x-filament::button color="gray" wire:click="nextDay">Next Day</x-filament::button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ($this->getKpis() as $label => $value)
                <div class="hr-kpi">
                    <p class="text-xs font-medium text-[#A7B0C0]">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-[#F8FAFC]">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="hr-filter-bar">
            <div class="grid gap-3 md:grid-cols-5">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="employeeId">
                        <option value="">All employees</option>
                        @foreach ($this->getEmployees() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="department">
                        <option value="">All departments</option>
                        @foreach ($this->getDepartments() as $department)
                            <option value="{{ $department }}">{{ $department }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        <option value="">All attendance statuses</option>
                        @foreach ($this->getAttendanceStatusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="workState">
                        <option value="">All work states</option>
                        @foreach ($this->getWorkStateOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::button color="gray" wire:click="resetFilters">Reset Filters</x-filament::button>
            </div>
        </div>

        <div class="hr-card overflow-x-auto">
            <table class="w-full min-w-[56rem] divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-[#1B2436]">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#A7B0C0]">
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">State</th>
                        <th class="px-4 py-3">Worked</th>
                        <th class="hidden px-4 py-3 md:table-cell">Remaining / Extra</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="hidden px-4 py-3 lg:table-cell">Sessions</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->getDailyRows() as $row)
                        <tr class="transition hover:bg-[rgba(124,58,237,0.06)]">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[rgba(124,58,237,0.15)] text-sm font-bold text-[#C4B5FD]">
                                        {{ collect(explode(' ', $row['employee_name']))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-[#F8FAFC]">{{ $row['employee_name'] }}</div>
                                        <div class="text-xs text-[#A7B0C0]">{{ $row['employee_number'] }} · {{ $row['department'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><x-filament::badge :color="$row['work_state_color']">{{ $row['work_state_label'] }}</x-filament::badge></td>
                            <td class="px-4 py-3 font-medium">
                                <div>{{ $row['worked'] }}</div>
                                <div class="mt-1 hr-progress w-28"><div class="hr-progress-fill" style="width: {{ $row['progress'] }}%"></div></div>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $row['remaining_extra'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['status'])
                                    <x-filament::badge :color="$row['status_color']">{{ $row['status_label'] }}</x-filament::badge>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">{{ $row['session_count'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['session_count'] > 0)
                                    <x-filament::button size="sm" color="gray" wire:click="viewSessions({{ $row['employee']->id }})">View</x-filament::button>
                                @else
                                    <span class="text-gray-400">No sessions</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#A7B0C0]">No attendance records found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($details = $this->getSelectedSessionDetails())
            <div class="fixed inset-0 z-40 bg-gray-950/50" wire:click="closeSessions"></div>
            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl overflow-y-auto bg-[#151C2C] p-6 text-[#F8FAFC] shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[#F8FAFC]">{{ $details['employee']->user->name }}</h2>
                        <p class="text-sm text-[#A7B0C0]">{{ $details['employee']->employee_number }} · {{ $details['employee']->department }} · {{ $details['date'] }}</p>
                    </div>
                    <x-filament::button color="gray" wire:click="closeSessions">Close</x-filament::button>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-5">
                    @foreach ($details['summary'] as $label => $value)
                        <div class="rounded-2xl border border-[#293449] bg-[#1B2436] p-3">
                            <p class="text-xs capitalize text-[#A7B0C0]">{{ $label }}</p>
                            <p class="font-semibold text-[#F8FAFC]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($details['sessions'] as $session)
                        <div class="rounded-2xl border border-[#293449] bg-[#1B2436] p-4 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="font-medium text-[#F8FAFC]">{{ $session['check_in'] }} -> {{ $session['check_out'] }}</div>
                                <x-filament::badge :color="$session['state'] === 'Working' ? 'success' : 'gray'">{{ $session['state'] }}</x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $session['duration'] }} · {{ $session['source'] }}</p>
                            @if ($session['created_by'])
                                <p class="mt-2 text-sm text-[#A7B0C0]">Added By: {{ $session['created_by'] }}</p>
                            @endif
                            @if ($session['note'])
                                <p class="text-sm text-[#A7B0C0]">Reason: {{ $session['note'] }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-[#293449] p-6 text-center text-[#A7B0C0]">No attendance sessions recorded for this employee on this date.</div>
                    @endforelse
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
