<div class="h-[calc(100vh-8rem)] flex flex-col" wire:key="site-preview"
     data-olx-origin="{{ $clientOrigin }}"
     x-data="{
        device: window.innerWidth <= 640 ? 'mobile' : (window.innerWidth <= 1024 ? 'tablet' : 'desktop'),
        init() {
            // Client iframe → CMS: a component was clicked in edit mode.
            // Trust ONLY the configured client site's origin — any other frame
            // could postMessage forged edits through the editor's session.
            window.addEventListener('message', (e) => {
                if (e.origin !== this.$root.dataset.olxOrigin) return;
                const d = e.data;
                if (!d || d.source !== 'olx-connect') return;
                if (d.type === 'olx-edit-select') this.$wire.onEditSelect(d.id, d.key, d.kind);
                if (d.type === 'olx-field-edit') this.$wire.inlineFieldEdit(d.id, d.key, d.kind, d.field, d.value, d.itemId);
                if (d.type === 'olx-item-remove') this.$wire.inlineItemRemove(d.id, d.key, d.itemId);
                if (d.type === 'olx-item-add') this.$wire.inlineItemAdd(d.id, d.key, d.componentKey, d.field);
                if (d.type === 'olx-node-item-add') this.$wire.inlineNodeItemAdd(d.key, d.prefix);
                if (d.type === 'olx-navigate') this.$wire.set('previewPath', d.path || '/');
                if (d.type === 'olx-item-remove-idx') this.$wire.inlineItemRemoveByIndex(d.key, d.index);
                if (d.type === 'olx-field-edit-idx') this.$wire.inlineFieldEditByIndex(d.key, d.field, d.value, d.index);
                if (d.type === 'olx-link-edit') this.$wire.inlineLinkEdit(d.key, d.kind, d.labelField ?? '', d.label, d.href, d.index ?? null, d.oldLabel ?? '', d.oldHref ?? '');
                if (d.type === 'olx-register') this.$wire.registerMarkers(d.markers);
                if (d.type === 'olx-hover-field') this.hotNode(d.field);
            });
            // Renderer mode: after a save, tell the shell to re-fetch content
            // and re-render IN PLACE — no iframe reload, no flash.
            Livewire.on('olx-refresh-frame', () => {
                const f = document.getElementById('olx-frame');
                if (f && f.contentWindow) f.contentWindow.postMessage({ source: 'olx-cms', type: 'olx-refresh-content' }, '*');
            });
            // Hard reload fallback (kept for callers that still dispatch it).
            Livewire.on('olx-reload-frame', () => {
                const f = document.getElementById('olx-frame');
                if (!f) return;
                const u = new URL(f.src, window.location.origin);
                u.searchParams.set('t', Date.now().toString());
                f.src = u.toString();
            });
        },
        // Preview field hover → highlight the matching node input on the right.
        hotNode(field) {
            document.querySelectorAll('[data-node-field].olx-node-hot')
                .forEach(el => el.classList.remove('olx-node-hot'));
            if (!field) return;
            const rows = [...document.querySelectorAll('[data-node-field]')];
            const want = field.toLowerCase();
            const el = rows.find(r => r.dataset.nodeField.toLowerCase() === want)
                || rows.find(r => r.dataset.nodeField.toLowerCase() === want.split('.').pop());
            if (el) { el.classList.add('olx-node-hot'); el.scrollIntoView({ block: 'nearest' }); }
        },
        // Bring the inspector into view when content is selected; jump to the
        // newest item row after an add.
        focusEditor(target) {
            this.$nextTick(() => {
                const panel = document.getElementById('olx-inspector');
                if (!panel) return;
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                const flash = el => { if (!el) return; el.classList.remove('olx-flash'); void el.offsetWidth; el.classList.add('olx-flash'); };
                const rows = panel.querySelectorAll('[data-item-row]');
                if (target === 'last-item' && rows.length) {
                    rows[rows.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    flash(rows[rows.length - 1]);
                } else if (target === 'items') {
                    const list = panel.querySelector('[data-items-list]') || panel;
                    list.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    flash(list);
                } else { panel.scrollTop = 0; }
            });
        },
     }"
     x-on:olx-editor-focus.window="focusEditor($event.detail?.target)">
    <style>
        .olx-in { width:100%; margin-top:2px; padding:.4rem .55rem; font-size:12px; border-radius:8px;
                  background:rgba(0,0,0,.02); border:1px solid rgba(0,0,0,.1); color:inherit; }
        .dark .olx-in { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.1); }
        .olx-save { margin-top:.75rem; width:100%; padding:.5rem; border-radius:12px; font-weight:700;
                    font-size:13px; color:#fff; background:var(--primary); }
        .olx-card { border:1px solid rgba(0,0,0,.08); border-radius:9px; padding:.5rem; font-size:12px; }
        .dark .olx-card { border-color:rgba(255,255,255,.08); }
        /* Inspector row lit up while its field is hovered in the preview */
        [data-node-field].olx-node-hot { outline:2px solid #6366f1; outline-offset:1px; border-radius:10px;
                                         background:rgba(99,102,241,.08); }
        /* One-shot attention flash after item add/remove */
        .olx-flash { animation: olxflash 1.2s ease; border-radius:10px; }
        @keyframes olxflash { 0% { background: rgba(99,102,241,.22); box-shadow: 0 0 0 2px rgba(99,102,241,.55); }
                              100% { background: transparent; box-shadow: none; } }
    </style>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-3 flex-wrap">
        <h1 class="text-lg font-extrabold text-gray-900 dark:text-white">Edit mode</h1>
        @if ($livePreviewUrl)
            <a href="{{ $livePreviewUrl }}" target="_blank" rel="noopener"
               class="text-xs font-semibold text-indigo-500 hover:text-indigo-600 whitespace-nowrap"
               title="Open this page exactly as visitors see it — no edit chrome">Live preview ↗</a>
        @endif

        {{-- Page selector: navigates the preview iframe to that page --}}
        @if ($pages->isNotEmpty())
            {{-- Page selector — hidden when embedded in a single page's Content tab --}}
            @unless($embedded)
            <select wire:model.live="previewPath"
                    class="text-xs font-semibold rounded-lg bg-white dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.1] px-2.5 py-1.5">
                @foreach ($pages as $page)
                    <option value="{{ $page->url }}">{{ $page->name }} ({{ $page->url }})</option>
                @endforeach
            </select>
            @endunless
        @endif

        <span class="text-xs text-gray-400">Click a component in the live preview to edit it.</span>

        {{-- Device preview: resizes the iframe to phone / tablet / full width --}}
        <div class="flex items-center gap-1 rounded-xl border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.04] p-1 shadow-sm">
            @foreach ([
                'mobile' => ['Mobile', 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
                'tablet' => ['Tablet', 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                'desktop' => ['Desktop', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ] as $dev => [$label, $path])
                <button type="button" @click="device = '{{ $dev }}'" title="Preview at {{ strtolower($label) }} width"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                        :class="device === '{{ $dev }}' ? 'text-white' : 'text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.08]'"
                        :style="device === '{{ $dev }}' ? 'background:var(--primary)' : ''">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                    <span class="hidden xl:inline">{{ $label }}</span>
                </button>
            @endforeach
        </div>

        @unless($embedded)
        <div class="ml-auto flex items-center gap-2">
            <button wire:click="publish" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.1]">Publish page.json</button>
            <a href="{{ route('site.connect.export', ['siteID' => $site->name]) }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.1]">Download export</a>
        </div>
        @endunless
        @if ($flash)
            <span class="w-full text-xs font-semibold" style="color:var(--primary)">{{ $flash }}</span>
        @endif
    </div>

    {{-- Client URL bar (hidden when embedded in the page-detail Content tab) --}}
    @unless($embedded)
        <div class="flex items-center gap-2 mb-3">
        <span class="text-xs font-semibold text-gray-500 shrink-0">Client site URL</span>
        <input wire:model="urlInput" type="url" placeholder="https://your-client-site.com (or http://localhost:3000)"
               class="flex-1 text-sm rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3 py-1.5">
        <button wire:click="saveClientUrl" class="text-xs font-semibold text-white px-3 py-1.5 rounded-lg" style="background:var(--primary)">Set</button>
    </div>
    @endunless

    @if ($this->installStatus === 'installing')
        {{-- The design was just applied; pages, forms and modules are being
             created by a background job. Poll until it flips to done/failed —
             the poll lives only in this branch, so it stops automatically. --}}
        <div wire:poll.3s class="flex-1 grid place-items-center border border-dashed rounded-2xl px-6 text-center">
            <div class="space-y-3">
                <svg class="animate-spin h-8 w-8 mx-auto text-indigo-500" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Setting up your site…</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto">Creating the template's pages and components, wiring up forms, bookings, shop &amp; orders, and applying the theme. This usually takes a few seconds — the preview appears here automatically.</p>
            </div>
        </div>
    @elseif ($this->installStatus === 'failed')
        <div class="flex-1 grid place-items-center border border-dashed border-rose-300 dark:border-rose-500/40 rounded-2xl px-6 text-center">
            <div class="space-y-3">
                <p class="text-sm font-semibold text-rose-600">Something went wrong while setting up this design.</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto">Nothing already on your site was touched. You can safely try again.</p>
                <button wire:click="retryInstall" class="text-xs font-semibold text-white px-4 py-2 rounded-lg" style="background:var(--primary)">Try again</button>
            </div>
        </div>
    @elseif (! $embedUrl)
        <div class="flex-1 grid place-items-center text-sm text-gray-400 border border-dashed rounded-2xl px-6 text-center">
            No preview available yet. Apply a design from <a href="{{ url($site->name.'/designs') }}" class="font-semibold text-indigo-500 hover:underline">My Designs</a> and your site shows here with live content — or, for an externally hosted client site, enter its URL above (it must embed <code>connect.js</code>) to preview and click-to-edit it.
        </div>
    @else
        {{-- Inspector only opens once a component is selected; otherwise the
             preview takes the full width. --}}
        <div class="flex-1 grid {{ $selectedKind ? 'lg:grid-cols-[1fr_360px]' : '' }} gap-4 min-h-0">

            {{-- Live client site (edit mode); width follows the device toggle --}}
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                 :class="device === 'desktop' ? 'bg-white' : 'bg-gray-100 dark:bg-black/30'">
                <div class="h-full mx-auto bg-white transition-all duration-300 overflow-hidden"
                     :style="device === 'mobile' ? 'max-width:390px' : device === 'tablet' ? 'max-width:768px' : 'max-width:100%'"
                     :class="device !== 'desktop' && 'shadow-lg'">
                    <iframe id="olx-frame" src="{{ $embedUrl }}" class="w-full h-full" style="border:0"></iframe>
                </div>
            </div>

            {{-- Inspector --}}
            @if ($selectedKind)
            <div id="olx-inspector" class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] p-4 overflow-y-auto">
                @if (! $edit)
                    <p class="text-sm text-gray-400">Hover the preview — components outline in orange. Click one to edit it here.</p>
                @else
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $selectedKind }}</p>
                        <div class="flex items-center gap-1">
                            <button wire:click="viewOnly" class="text-[11px] font-semibold px-2 py-1 rounded-lg {{ $mode === 'view' ? 'text-white' : 'text-gray-500' }}" @if($mode==='view') style="background:var(--primary)" @endif>View</button>
                            <button wire:click="edit" class="text-[11px] font-semibold px-2 py-1 rounded-lg {{ $mode === 'edit' ? 'text-white' : 'text-gray-500' }}" @if($mode==='edit') style="background:var(--primary)" @endif>Edit</button>
                            @if ($mode === 'edit')
                                <button wire:click="save" title="Save {{ $edit['type'] ?? 'content' }}"
                                        class="ml-0.5 p-1.5 rounded-lg text-white hover:opacity-90" style="background:var(--primary)">
                                    <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                    <svg wire:loading wire:target="save" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 019 9"/></svg>
                                </button>
                            @endif
                            <button wire:click="deselect" title="Close panel"
                                    class="text-sm leading-none text-gray-400 hover:text-rose-600 ml-1 px-1">✕</button>
                        </div>
                    </div>
                    <p class="mt-1 text-sm font-extrabold text-gray-900 dark:text-white">{{ $edit['name'] ?? $edit['title'] ?? '' }}</p>

                    @if ($mode === 'edit')
                        @include('livewire.partials.connect-editor')

                        {{-- ── History: revert to one of the recent snapshots ── --}}
                        @if ($versions->isNotEmpty())
                            <p class="mt-5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">History</p>
                            <div class="mt-1.5 space-y-1.5">
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
                @endif
            </div>
            @endif
        </div>
    @endif

    {{-- Asset library modal — opened by the "Assets" button on image fields;
         selection returns a portable @media ref via the media-picked event. --}}
    <livewire:media-picker :site-id="$site->id" :key="'connect-media-picker-'.$site->id" />

</div>
