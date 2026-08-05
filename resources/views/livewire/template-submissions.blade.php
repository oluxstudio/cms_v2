<div>
@if($isModerator)
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Template submissions</h2>
            <p class="text-[11px] text-gray-400">Nuxt apps in the staging folder — extracted, reviewed, then published to the marketplace.</p>
        </div>
        <button wire:click="scan" wire:loading.attr="disabled" wire:target="scan"
                class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 text-white text-xs font-semibold">
            <span wire:loading.remove wire:target="scan">Scan staging folder</span>
            <span wire:loading wire:target="scan">Scanning…</span>
        </button>
    </div>

    @forelse($subs as $sub)
        @php $s = $sub->summary(); @endphp
        <div wire:key="sub-{{ $sub->id }}" x-data="{ open: false }"
             class="mb-3 rounded-2xl border bg-white dark:bg-[#1e1f2b] overflow-hidden
                {{ $sub->status === 'pending' ? 'border-amber-300 dark:border-amber-400/40' : ($sub->status === 'accepted' ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-rose-200 dark:border-rose-500/30') }}">
            <div class="p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-lg leading-none">🧩</span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $sub->name }} <span class="font-mono text-[10px] text-gray-400">{{ $sub->key }}</span></p>
                        <p class="text-[11px] text-gray-400">Nuxt 4 app · scanned {{ $sub->updated_at->diffForHumans() }}</p>
                    </div>
                    <span class="ml-auto px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wide
                        {{ $sub->status === 'pending' ? 'bg-amber-100 dark:bg-amber-400/15 text-amber-600 dark:text-amber-300' : ($sub->status === 'accepted' ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300') }}">
                        {{ $sub->status }}
                    </span>
                </div>

                {{-- Extraction summary --}}
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 font-semibold">✓ {{ $s['pages'] }} {{ Str::plural('page', $s['pages']) }} · {{ $s['blocks'] }} blocks</span>
                    <span class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 font-semibold">✓ {{ count($s['theme']) }} theme tokens · {{ implode(', ', $s['fonts']) ?: 'no fonts' }}</span>
                    <span class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 font-semibold">✓ {{ $s['assets'] }} assets</span>
                    @foreach($s['behaviours'] as $b)
                        <span class="px-2 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 font-semibold">{{ $b }}</span>
                    @endforeach
                    {{-- Theme swatches --}}
                    @foreach($s['theme'] as $prop => $val)
                        @if(str_starts_with($prop, 'color-'))
                            <span class="inline-block w-4 h-4 rounded-full border border-black/10" style="background: {{ $val }}" title="--{{ $prop }}: {{ $val }}"></span>
                        @endif
                    @endforeach
                </div>

                {{-- Quality lint: how faithfully can the CMS edit this template? --}}
                @php $lint = app(\App\Services\TemplateLint::class)->analyze($sub->extraction ?? [], $sub->stagingPath()); @endphp
                <div class="mt-3" x-data="{ lint: false }">
                    <button @click="lint = !lint"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-semibold border
                                {{ $lint['score'] >= 80 ? 'border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-300' : ($lint['score'] >= 50 ? 'border-amber-300 dark:border-amber-400/40 text-amber-600 dark:text-amber-300' : 'border-rose-300 dark:border-rose-500/40 text-rose-600 dark:text-rose-300') }}">
                        Quality {{ $lint['score'] }}/100
                        <span class="text-[10px] font-normal opacity-70" x-text="lint ? 'hide' : 'details'"></span>
                    </button>
                    <div x-show="lint" x-cloak class="mt-2 space-y-1">
                        @foreach($lint['findings'] as $lf)
                            <p class="text-[11px] leading-snug flex items-start gap-1.5">
                                <span class="shrink-0 mt-px font-bold {{ $lf['level'] === 'error' ? 'text-rose-500' : ($lf['level'] === 'warning' ? 'text-amber-500' : 'text-emerald-500') }}">
                                    {{ $lf['level'] === 'error' ? '✕' : ($lf['level'] === 'warning' ? '⚠' : '✓') }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-300"><strong class="font-semibold">{{ $lf['area'] }}</strong> — {{ $lf['message'] }}</span>
                            </p>
                        @endforeach
                    </div>
                </div>

                @if($sub->status === 'rejected' && $sub->note)
                    <p class="mt-2 text-[11px] text-rose-500">Rejected: {{ $sub->note }}</p>
                @endif

                {{-- Actions --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button @click="open = !open" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]"
                            x-text="open ? 'Hide blocks' : 'Inspect blocks'"></button>

                    @if($url = $this->previewUrl($sub))
                        <a href="{{ $url }}" target="_blank" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]">Open preview ↗</a>
                        <button wire:click="buildPreview({{ $sub->id }})" class="px-2.5 py-1.5 rounded-lg text-[11px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Rebuild the preview">↻ Rebuild</button>
                    @else
                        <button wire:click="buildPreview({{ $sub->id }})" wire:loading.attr="disabled"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]">Build preview</button>
                    @endif

                    @if($sub->status !== 'accepted')
                        <button wire:click="accept({{ $sub->id }})" data-confirm="Publish “{{ $sub->name }}” to the marketplace? Its block components will be rewritten for content editing."
                                wire:loading.attr="disabled" wire:target="accept"
                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-70 text-white text-xs font-semibold">
                            <span wire:loading.remove wire:target="accept">Accept & publish</span>
                            <span wire:loading wire:target="accept">Publishing…</span>
                        </button>
                        <button wire:click="startReject({{ $sub->id }})"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-500 border border-rose-200 dark:border-rose-500/30 hover:bg-rose-50 dark:hover:bg-rose-500/10">Reject</button>
                    @else
                        <button wire:click="accept({{ $sub->id }})" data-confirm="Re-publish “{{ $sub->name }}” with the latest staging code and extraction?"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-white/[0.08] hover:bg-gray-50 dark:hover:bg-white/[0.05]">Re-publish update</button>
                    @endif
                </div>

                {{-- Reject note --}}
                @if($rejectingId === $sub->id)
                    <div class="mt-3 flex items-center gap-2">
                        <input wire:model="rejectNote" wire:keydown.enter="reject" type="text" placeholder="Reason for rejection…"
                               class="flex-1 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-rose-500/40">
                        <button wire:click="reject" data-confirm="Reject this template submission?" class="px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">Confirm reject</button>
                        <button wire:click="$set('rejectingId', null)" class="px-2 py-2 text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                    </div>
                @endif
            </div>

            {{-- Extracted block detail --}}
            <div x-show="open" x-collapse x-cloak>
                <div class="border-t border-gray-100 dark:border-white/[0.06] px-4 py-3 space-y-2 bg-gray-50/50 dark:bg-white/[0.02]">
                    @foreach($sub->extraction['pages'] ?? [] as $p)
                        <p class="text-[11px] font-bold text-gray-600 dark:text-gray-300">{{ $p['name'] }} <span class="font-mono font-normal text-gray-400">{{ $p['url'] }}</span>
                            @if($p['layout']['header'])<span class="font-normal text-gray-400"> · header: {{ $p['layout']['header'] }}</span>@endif
                            @if($p['layout']['footer'])<span class="font-normal text-gray-400"> · footer: {{ $p['layout']['footer'] }}</span>@endif
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            @foreach($p['blocks'] as $b)
                                <div class="rounded-lg border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-white/[0.02] px-2.5 py-1.5">
                                    <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-200">{{ $b['name'] }} <span class="font-mono text-[9px] text-gray-400">{{ $b['blockKey'] }}</span></p>
                                    @php $bGroups = $b['items'] ? (array_is_list($b['items']) ? $b['items'] : [$b['items']]) : []; @endphp
                                    <p class="text-[10px] text-gray-400">{{ count($b['nodes']) }} editable {{ Str::plural('field', count($b['nodes'])) }}@foreach($bGroups as $g) · {{ $g['count'] }}× {{ $g['prefix'] }} items (+ addable)@endforeach</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] p-10 text-center">
            <p class="text-sm text-gray-400">No submissions yet.</p>
            <p class="text-xs text-gray-400 mt-1">Drop a Nuxt template app into the staging folder and hit “Scan staging folder”.</p>
        </div>
    @endforelse
@endif
</div>
