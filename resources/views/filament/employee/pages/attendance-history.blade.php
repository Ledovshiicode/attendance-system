<x-filament-panels::page>
    <div class="space-y-4">
        <div class="hr-card-pad">
            <h2 class="text-2xl font-semibold tracking-tight text-[#F8FAFC]">Attendance History</h2>
            <p class="mt-1 text-sm hr-muted">Review daily worked time and open session details without the clutter.</p>
        </div>

        @foreach($days as $day)
            <div wire:click="selectDay('{{ $day['date'] }}')"
                 class="hr-card cursor-pointer p-4 transition hover:-translate-y-0.5 hover:shadow-[0_14px_36px_rgba(17,24,39,0.07)]
                        {{ $selectedDay === $day['date'] ? 'ring-2 ring-[#7C3AED]' : '' }}">

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-[#F8FAFC]">{{ $day['date_display'] }}</h3>
                        <p class="text-sm text-[#A7B0C0]">{{ $day['session_count'] }} {{ $day['session_count'] === 1 ? 'session' : 'sessions' }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <div class="text-right">
                            <p class="text-lg font-bold text-[#F8FAFC]">{{ $day['worked_display'] }}</p>
                            <p class="text-xs text-[#A7B0C0]">of {{ $day['required_display'] }} required</p>
                        </div>

                        <x-filament::badge :color="$day['work_state_color']">{{ $day['work_state_label'] }}</x-filament::badge>
                        <x-filament::badge :color="$day['status_color']">{{ $day['status_label'] }}</x-filament::badge>
                    </div>
                </div>
            </div>

            @if($selectedDay === $day['date'] && count($selectedDaySessions) > 0)
                <div class="space-y-2 sm:ml-6">
                    @foreach($selectedDaySessions as $session)
                        <div class="flex flex-col gap-2 rounded-2xl border border-[#293449] bg-[#1B2436] px-4 py-3 text-sm shadow-sm sm:flex-row sm:items-center sm:gap-8">
                            <div>
                                <span class="text-[#A7B0C0]">In:</span>
                                <span class="font-medium text-[#F8FAFC]">{{ $session['check_in'] }}</span>
                            </div>
                            <div>
                                <span class="text-[#A7B0C0]">Out:</span>
                                <span class="font-medium text-[#F8FAFC]">{{ $session['check_out'] }}</span>
                            </div>
                            <div>
                                <span class="text-[#A7B0C0]">Duration:</span>
                                <span class="font-medium text-[#F8FAFC]">{{ $session['duration'] }}</span>
                            </div>
                            <div>
                                <span class="text-[#A7B0C0]">Source:</span>
                                <span class="font-medium text-[#F8FAFC]">{{ $session['source'] }}</span>
                            </div>
                            <x-filament::badge :color="$session['is_open'] ? 'success' : 'gray'" size="sm">
                                {{ $session['is_open'] ? 'Working' : 'Closed' }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @elseif($selectedDay === $day['date'])
                <div class="rounded-lg border border-dashed border-[#293449] p-4 text-sm text-[#A7B0C0] sm:ml-6">
                    No attendance sessions recorded for this employee on this date.
                </div>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
