{{-- Today's & tomorrow's bookings. --}}
@if ($site->hasFeature('bookings'))
<div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
    <div class="flex items-center justify-between mb-2.5">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Today's bookings</span>
        <a href="{{ url($site->name.'/bookings') }}" class="text-[10px] font-bold text-indigo-500 hover:underline">Diary →</a>
    </div>
    @forelse ($agenda as $b)
        <div class="flex items-center gap-3 py-1.5 {{ ! $loop->last ? 'border-b border-gray-50 dark:border-white/[0.04]' : '' }}">
            <span class="w-14 shrink-0 text-xs font-extrabold {{ $b['day'] === 'Today' ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $b['time'] }}<span class="block text-[9px] font-medium text-gray-400">{{ $b['day'] }}</span></span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $b['customer'] }}</span>
                <span class="block text-[10px] text-gray-400 truncate">{{ $b['service'] ?? 'Booking' }}{{ $b['status'] === 'pending' ? ' · awaiting confirmation' : '' }}</span>
            </span>
            @if ($b['balance_cents'] > 0)
                <span class="text-[10px] font-bold text-amber-600 shrink-0" title="Balance owed">£{{ number_format($b['balance_cents'] / 100, 2) }} due</span>
            @endif
        </div>
    @empty
        <p class="text-xs text-gray-400 py-3">Nothing booked for today or tomorrow.</p>
    @endforelse
</div>
@endif
