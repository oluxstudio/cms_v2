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
        /* The panel's sticky footer owns Save — hide the editors' inline ones */
        #olx-inspector .olx-save { display:none; }
        /* The editor panel owns the bottom-right corner while open — the chat
           FAB would float over the Save bar, so hide it for the session. */
        body:has(#olx-inspector) #bk-chat-fab { display:none; }
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

    {{-- Toolbar — hidden on mobile when embedded in the page Content tab --}}
    <div class="{{ $embedded ? 'hidden lg:flex' : 'flex' }} items-center gap-3 mb-3 flex-wrap">
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
        @if ($embedded)
        {{-- Embedded (page-detail Content tab): compact two-pane layout --}}
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

            @if ($selectedKind)
            {{-- Mobile: bottom sheet UNDER the site header (top-16) so the menu
                 and nav stay reachable; z-[45] paints it OVER the floating pane
                 switcher + assistant bubble (z-40) but under header menus (z-50);
                 desktop: normal side column. --}}
            <div id="olx-inspector"
                 x-data x-init="if (window.innerWidth < 1024) requestAnimationFrame(() => $el.classList.remove('translate-y-full'))"
                 class="translate-y-full lg:translate-y-0 transition-transform duration-300
                        fixed inset-x-0 bottom-0 top-16 z-[45] w-full shadow-2xl rounded-t-2xl
                        lg:static lg:w-auto lg:shadow-none lg:z-auto lg:rounded-2xl
                        border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] overflow-hidden flex flex-col">
                {{-- Sheet header: drag hint + always-visible close --}}
                <div class="lg:hidden shrink-0 flex items-center justify-between px-4 pt-2.5 pb-2 border-b border-gray-100 dark:border-white/[0.06]">
                    <span class="w-8"></span>
                    <span class="w-10 h-1.5 rounded-full bg-gray-200 dark:bg-white/15"></span>
                    <button wire:click="deselect" aria-label="Close editor"
                            class="w-8 h-8 flex items-center justify-center rounded-full text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.08] hover:text-rose-600">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    @include('livewire.partials.connect-inspector')
                </div>
            </div>
            @endif
        </div>
        @else
        {{-- Edit mode is a 3-slide layout: pages | live preview | editor.
             Mobile swipes between the slides; desktop shows all three. --}}
        <x-carousel :labels="['📄 Pages', '🖥 Preview', '✏️ Edit']" :start="1">

        {{-- ════ LEFT: pages ════ --}}
        <x-carousel.slide class="lg:!w-[220px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] p-2">
                <p class="px-2 pt-1 pb-2 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">Pages</p>
                @foreach ($pages as $page)
                    <button wire:click="$set('previewPath', '{{ $page->url }}')"
                            class="w-full text-left px-2.5 py-2 rounded-xl text-xs font-semibold transition-colors
                                   {{ $previewPath === $page->url
                                       ? 'text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]' }}"
                            @if($previewPath === $page->url) style="background:var(--primary)" @endif>
                        {{ $page->name }}
                        <span class="block font-mono text-[10px] {{ $previewPath === $page->url ? 'text-white/70' : 'text-gray-400' }}">{{ $page->url }}</span>
                    </button>
                @endforeach
            </div>
        </x-carousel.slide>

        {{-- ════ MIDDLE: live preview ════ --}}
        <x-carousel.slide class="lg:flex-1 lg:min-w-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">
            <div class="h-[70vh] lg:h-[calc(100vh-13rem)] flex flex-col">
            {{-- Live client site (edit mode); width follows the device toggle --}}
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                 :class="device === 'desktop' ? 'bg-white' : 'bg-gray-100 dark:bg-black/30'">
                <div class="h-full mx-auto bg-white transition-all duration-300 overflow-hidden"
                     :style="device === 'mobile' ? 'max-width:390px' : device === 'tablet' ? 'max-width:768px' : 'max-width:100%'"
                     :class="device !== 'desktop' && 'shadow-lg'">
                    <iframe id="olx-frame" src="{{ $embedUrl }}" class="w-full h-full" style="border:0"></iframe>
                </div>
            </div>
            </div>
        </x-carousel.slide>

        {{-- ════ RIGHT: content editor ════ --}}
        <x-carousel.slide class="lg:!w-[380px] lg:shrink-0 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            <div id="olx-inspector" class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] p-4">
                @if (! $selectedKind)
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">✏️ Edit content</p>
                    <p class="mt-2 text-sm text-gray-400">Click any section in the preview — it outlines in orange and its content opens here to edit.</p>
                @else
                    @include('livewire.partials.connect-inspector')
                @endif
            </div>
        </x-carousel.slide>
        </x-carousel>
        @endif
    @endif

    {{-- Asset library modal — opened by the "Assets" button on image fields;
         selection returns a portable @media ref via the media-picked event. --}}
    <livewire:media-picker :site-id="$site->id" :key="'connect-media-picker-'.$site->id" />

</div>
