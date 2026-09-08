{{-- Generated action items — things that need a human, produced by OpsAlerts. --}}
@if ($actionItems)
<div class="rounded-3xl p-4 shadow-sm bg-rose-50/70 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20">
    <div class="flex items-center justify-between mb-2.5">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Needs your attention</span>
        <a href="{{ url($site->name.'/alerts') }}" class="text-[10px] font-bold text-rose-500 hover:underline">All alerts →</a>
    </div>
    <div class="space-y-2">
        @foreach ($actionItems as $a)
            <a href="{{ url($a['link'] ?? $site->name.'/alerts') }}" class="block rounded-xl bg-white/80 dark:bg-white/[0.04] px-3 py-2 hover:shadow-sm transition-shadow">
                <p class="text-xs font-bold {{ ($a['level'] ?? '') === 'warning' ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-gray-100' }}">{{ $a['title'] }}</p>
                @if (! empty($a['body']))<p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $a['body'] }}</p>@endif
            </a>
        @endforeach
    </div>
</div>
@endif
