{{-- Vertical pack — the numbers this KIND of business checks each morning. --}}
@if ($verticalStats)
<div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $verticalStats['pack'] === 'salon' ? 'Salon pulse' : 'Jobs & quotes' }}</span>
        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-500">{{ ucfirst($verticalStats['pack']) }}</span>
    </div>

    <div class="grid grid-cols-3 gap-2 text-center mb-3">
        @foreach ($verticalStats['tiles'] as $t)
            <div class="rounded-xl px-1 py-2 {{ ($t['flag'] ?? false) ? 'bg-rose-50 dark:bg-rose-500/10' : 'bg-gray-50 dark:bg-white/[0.04]' }}">
                <p class="text-xl font-extrabold leading-none {{ ($t['flag'] ?? false) ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">{{ $t['value'] }}</p>
                <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 mt-1">{{ $t['label'] }}</p>
                <p class="text-[9px] text-gray-400 leading-tight">{{ $t['hint'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($verticalStats['list'])
        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">{{ $verticalStats['list_title'] }}</p>
        <div class="space-y-1">
            @foreach ($verticalStats['list'] as $row)
                <a href="{{ url($row['href']) }}" class="flex items-baseline justify-between gap-2 text-xs rounded-lg px-1.5 py-1 hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                    <span class="font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $row['title'] }}</span>
                    <span class="text-[10px] text-gray-400 shrink-0">{{ $row['sub'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($verticalStats['histogram'])
        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mt-3 mb-1.5">Busiest hours · 7 days</p>
        @php $max = max(1, collect($verticalStats['histogram'])->max('count')); @endphp
        <div class="flex items-end gap-0.5 h-12">
            @foreach ($verticalStats['histogram'] as $bar)
                <div class="flex-1 flex flex-col items-center gap-0.5" title="{{ $bar['hour'] }}:00 — {{ $bar['count'] }}">
                    <div class="w-full rounded-sm" style="height:{{ max(6, round($bar['count'] / $max * 100)) }}%;background:{{ $bar['count'] > 0 ? '#6366f1' : 'rgba(148,163,184,.25)' }}"></div>
                    @if ($bar['hour'] % 4 === 0)<span class="text-[8px] text-gray-400">{{ $bar['hour'] }}</span>@endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endif
