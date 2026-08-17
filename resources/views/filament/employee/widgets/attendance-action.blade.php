<div wire:poll.5s="refreshStatus" class="hr-card-pad overflow-hidden bg-gradient-to-br from-[#151C2C] to-[#1B2436]">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            @if($isWorking)
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                    </span>
                    <span class="text-lg font-semibold text-[#F8FAFC]">Working</span>
                </div>
                <p class="mt-1 text-sm text-[#A7B0C0]">
                    Working since <strong>{{ $openSessionCheckIn }}</strong>
                </p>
            @else
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-gray-400"></span>
                    </span>
                    <span class="text-lg font-semibold text-[#F8FAFC]">Today's Attendance</span>
                </div>
                <p class="mt-1 text-sm text-[#A7B0C0]">
                    <span class="text-2xl font-semibold text-[#F8FAFC]">{{ $workedToday }}</span>
                    <span class="ml-2">{{ $statusLabel }}</span>
                </p>
            @endif
            <div class="mt-4 max-w-sm hr-progress">
                <div class="hr-progress-fill" style="width: {{ $progress }}%"></div>
            </div>
        </div>

        <div>
            @if($isWorking)
                <x-filament::button wire:click="checkOut" wire:loading.attr="disabled" color="danger" size="lg">
                    Check Out
                </x-filament::button>
            @else
                <x-filament::button wire:click="checkIn" wire:loading.attr="disabled" color="success" size="lg">
                    {{ $workedToday === '0m' ? 'Check In' : 'Check In Again' }}
                </x-filament::button>
            @endif
        </div>
    </div>
</div>
