{{-- Go-live checklist — auto-detected, hidden once everything is done. --}}
@unless ($checklistProgress['complete'])
<div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-indigo-100 dark:border-indigo-500/20">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Get fully set up</span>
        <span class="text-[10px] font-bold text-indigo-500">{{ $checklistProgress['done'] }}/{{ $checklistProgress['total'] }}</span>
    </div>
    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden mb-3">
        <div class="h-full rounded-full bg-indigo-500 transition-all" style="width:{{ $checklistProgress['pct'] }}%"></div>
    </div>
    <div class="space-y-1.5">
        @foreach ($checklist as $step)
            <a href="{{ $step['done'] ? '#' : $step['cta_url'] }}" class="flex items-center gap-2.5 text-sm rounded-xl px-2 py-1.5 {{ $step['done'] ? 'opacity-50 pointer-events-none' : 'hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10' }}" title="{{ $step['description'] }}">
                <span class="w-4.5 h-4.5 w-[18px] h-[18px] rounded-full grid place-items-center text-[10px] shrink-0 {{ $step['done'] ? 'bg-emerald-500 text-white' : 'ring-1 ring-gray-300 dark:ring-white/20 text-transparent' }}">✓</span>
                <span class="flex-1 truncate {{ $step['done'] ? 'line-through text-gray-400' : 'text-gray-700 dark:text-gray-200' }}">{{ $step['label'] }}</span>
                @unless ($step['done'])<span class="text-[10px] font-bold text-indigo-500 shrink-0">{{ $step['cta_label'] }} →</span>@endunless
            </a>
        @endforeach
    </div>
</div>
@endunless
