{{-- "Set up your site" — rendered from the site's setup task (App\Support\SiteSetupTask);
     each row opens the page for that step. Hidden once every step is done. --}}
@if ($checklist && ! $checklistProgress['complete'])
<div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Set up your site</span>
        <span class="text-[11px] font-bold" style="color:var(--primary)">{{ $checklistProgress['done'] }}/{{ $checklistProgress['total'] }}</span>
    </div>
    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden mb-2.5">
        <div class="h-full rounded-full transition-all" style="width:{{ $checklistProgress['pct'] }}%;background:var(--primary)"></div>
    </div>
    <div class="space-y-0.5">
        @foreach ($checklist as $step)
            <a href="{{ $step['url'] ?? url($site->name.'/tasks?task='.$setupTaskId) }}" title="{{ $step['description'] }}"
               class="group flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                @if ($step['done'])
                    <span class="w-6 h-6 rounded-full grid place-items-center shrink-0 bg-green-600 text-white shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="flex-1 min-w-0 truncate text-[13.5px] font-semibold line-through text-gray-400">{{ $step['label'] }}</span>
                @else
                    <span class="w-6 h-6 rounded-full shrink-0 ring-2 ring-inset ring-gray-300 dark:ring-white/20 group-hover:ring-[color:var(--primary)] transition-colors"></span>
                    <span class="flex-1 min-w-0 truncate text-[13.5px] font-semibold text-gray-800 dark:text-gray-100">{{ $step['label'] }}</span>
                    <svg class="w-4 h-4 shrink-0 text-gray-300 group-hover:text-[color:var(--primary)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endif
            </a>
        @endforeach
    </div>
    <a href="{{ url($site->name.'/tasks?task='.$setupTaskId) }}" class="block text-right text-[11px] font-bold mt-2 hover:underline" style="color:var(--primary)">Open as task →</a>
</div>
@endif
