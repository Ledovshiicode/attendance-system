@php
    $user = \Filament\Facades\Filament::auth()->user();
    $hour = now()->timezone(config('app.timezone'))->hour;
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $role = \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin' ? 'Administrator' : 'Employee';
@endphp

<div class="hidden min-w-0 items-center gap-3 px-2 md:flex">
    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[rgba(124,58,237,0.15)] text-sm font-bold text-[#C4B5FD]">
        {{ collect(explode(' ', $user?->name ?? 'User'))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
    </div>
    <div class="min-w-0">
        <p class="text-xs font-medium text-[#A7B0C0]">{{ $greeting }}</p>
        <p class="truncate text-sm font-semibold text-[#F8FAFC]">{{ $user?->name }}</p>
    </div>
    <span class="rounded-full bg-[rgba(124,58,237,0.15)] px-3 py-1 text-xs font-semibold text-[#C4B5FD]">{{ $role }}</span>
</div>
