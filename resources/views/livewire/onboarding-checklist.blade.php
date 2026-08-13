<div>
@if ($open)
    <div class="mb-6 bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] rounded-2xl p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                @if ($progress['complete'])
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">You're all set 🎉</h3>
                    <p class="text-sm text-gray-400 mt-0.5">Nice work — your workspace is ready to go.</p>
                @else
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Get started</h3>
                    <p class="text-sm text-gray-400 mt-0.5">A few quick steps to your first result.</p>
                @endif
            </div>
            <button wire:click="dismiss" class="text-xs font-semibold text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                {{ $progress['complete'] ? 'Done' : 'Dismiss' }}
            </button>
        </div>

        {{-- Progress bar --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                <div class="h-full rounded-full bg-indigo-600 transition-all"
                     style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
            </div>
            <span class="text-xs font-semibold text-gray-500 shrink-0">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
        </div>

        {{-- Steps --}}
        <div class="space-y-2">
            @foreach ($steps as $step)
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-white/[0.06] px-3 py-2.5">
                    <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-sm font-bold
                                 {{ $step['done'] ? 'bg-emerald-500 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-400' }}">
                        {!! $step['done'] ? '&#10003;' : '' !!}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold {{ $step['done'] ? 'text-gray-400 line-through' : 'text-gray-800 dark:text-gray-200' }}">{{ $step['label'] }}</p>
                        @unless ($step['done'])<p class="text-xs text-gray-400 truncate">{{ $step['description'] }}</p>@endunless
                    </div>
                    @unless ($step['done'])
                        @if ($step['key'] === 'create_site')
                            <button wire:click="openCreate" class="shrink-0 text-xs font-semibold text-white px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700">{{ $step['cta_label'] }}</button>
                        @elseif ($step['cta_url'])
                            <a href="{{ $step['cta_url'] }}" wire:navigate class="shrink-0 text-xs font-semibold text-indigo-600 dark:text-indigo-400 px-3 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{{ $step['cta_label'] }}</a>
                        @else
                            <span class="shrink-0 text-[11px] text-gray-300 dark:text-gray-600">Create a site first</span>
                        @endif
                    @endunless
                </div>
            @endforeach
        </div>
    </div>
@endif
</div>
