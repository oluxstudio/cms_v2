{{-- Inspector body — the selected model's viewer/editor. Expects $edit, $mode, $selectedKind, $versions, $site.
     Layout: sticky highlighted header (identity + close) · scrollable body ·
     sticky highlighted footer (Save + collapsible checkpoints). The sticky
     bars ride the panel's own scroll container (side column or bottom sheet). --}}
@if (! $edit)
    <p class="text-sm text-gray-400">Hover the preview — components outline in orange. Click one to edit it here.</p>
@else
    {{-- ── Sticky header ── --}}
    <div class="sticky top-0 z-10 -mx-4 -mt-4 px-4 py-3 mb-3 border-b border-gray-100 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a]"
         style="box-shadow: inset 0 3px 0 var(--primary)">
        <div class="flex items-center justify-between gap-2">
            <span class="min-w-0">
                <span class="block text-[10px] font-bold uppercase tracking-wider" style="color:var(--primary)">{{ $selectedKind }}</span>
                <span class="block text-sm font-extrabold text-gray-900 dark:text-white truncate">{{ $edit['name'] ?? $edit['title'] ?? '' }}</span>
            </span>
            <span class="flex items-center gap-1 shrink-0">
                <button wire:click="viewOnly" class="text-[11px] font-semibold px-2 py-1 rounded-lg {{ $mode === 'view' ? 'text-white' : 'text-gray-500' }}" @if($mode==='view') style="background:var(--primary)" @endif>View</button>
                <button wire:click="edit" class="text-[11px] font-semibold px-2 py-1 rounded-lg {{ $mode === 'edit' ? 'text-white' : 'text-gray-500' }}" @if($mode==='edit') style="background:var(--primary)" @endif>Edit</button>
                <button wire:click="deselect" title="Close panel" aria-label="Close panel"
                        class="ml-1 w-8 h-8 flex items-center justify-center rounded-full text-white bg-rose-500 hover:bg-rose-600 shadow-sm text-sm leading-none">✕</button>
            </span>
        </div>
    </div>

    {{-- ── Body ── --}}
    @if ($mode === 'edit')
        @include('livewire.partials.connect-editor')
    @else
        @php $t = $edit['type']; @endphp
        @if ($t === 'component')
            <div class="mt-3 space-y-2">
                @foreach ($edit['nodes'] as $node)
                    <div data-node-field="{{ \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($node['label'])) }}" class="rounded-lg p-1 -m-1">
                        <p class="text-[11px] text-gray-400">{{ $node['label'] }}</p>
                        @if ($node['type'] === 'image' && $node['value'])
                            <img src="{{ \App\Models\Media::resolveRef($site->id, $node['value']) }}" alt="" class="max-h-28 rounded-lg">
                        @else
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ $node['value'] ?: '—' }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif ($t === 'collection')
            <p class="mt-3 text-[11px] text-gray-400">{{ count($edit['items']) }} item(s)</p>
            <div class="mt-2 space-y-2">
                @foreach ($edit['items'] as $item)
                    <div class="olx-card">
                        @foreach ($item['data'] as $k => $v)
                            <div><span class="text-gray-400">{{ $k }}:</span> {{ \Illuminate\Support\Str::limit((string) $v, 60) }}</div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @elseif ($t === 'form')
            <p class="mt-3 text-[11px] text-gray-400">Endpoint: {{ $edit['endpoint'] ?: 'CMS (form responses)' }}</p>
            <ul class="mt-2 text-sm text-gray-800 dark:text-gray-200 space-y-1">
                @foreach ($edit['fields'] as $field)
                    <li>{{ $field['label'] ?? $field['key'] ?? '' }} <span class="text-gray-400">({{ $field['type'] ?? 'text' }})</span></li>
                @endforeach
            </ul>
        @elseif ($t === 'post')
            <p class="mt-3 text-sm text-gray-500">{{ $edit['excerpt'] ?: '—' }}</p>
        @endif
    @endif

    {{-- ── Sticky footer: primary Save + collapsible checkpoints ── --}}
    @if ($mode === 'edit' || $versions->isNotEmpty())
    <div class="h-32 lg:hidden"></div> {{-- clearance so content scrolls past the fixed bar --}}
    <div class="fixed inset-x-0 bottom-0 z-[46] lg:sticky lg:bottom-0 lg:inset-x-auto lg:z-10 lg:-mx-4 lg:-mb-4 lg:mt-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] px-4 py-3 border-t border-gray-100 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] space-y-2"
         style="box-shadow: inset 0 -3px 0 var(--primary)"
         x-data="{ checkpoints: false }">
        @if ($versions->isNotEmpty())
            <button type="button" @click="checkpoints = ! checkpoints" :aria-expanded="checkpoints"
                    class="w-full flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">
                <span>🕘 Checkpoints ({{ $versions->count() }})</span>
                <svg class="w-3.5 h-3.5 transition-transform" :class="checkpoints ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="checkpoints" x-collapse x-cloak class="max-h-48 overflow-y-auto space-y-1.5">
                @foreach ($versions as $v)
                    <div class="flex items-center gap-2 olx-card">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $v->label ?: 'Snapshot' }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $v->created_at->diffForHumans() }}{{ $v->created_by ? ' · '.$v->created_by : '' }}</span>
                        </span>
                        <button wire:click="revertTo('{{ $v->id }}')"
                                data-confirm="Revert to this version? The current content will be saved to history first."
                                class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-white/[0.06]">
                            Revert
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($mode === 'edit')
            <button wire:click="save"
                    class="w-full py-2.5 rounded-xl text-sm font-bold text-white shadow-md hover:opacity-90" style="background:var(--primary)">
                <span wire:loading.remove wire:target="save">Save {{ $edit['type'] ?? 'content' }}</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        @endif
    </div>
    @endif
@endif
