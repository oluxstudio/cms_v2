{{-- lg:h-full, not a 100vh calc: #MainBody in the layout already hands this
     page its exact remaining height — a viewport guess overflowed by the
     header delta and gave the whole window a scrollbar. --}}
<div class="lg:h-full lg:overflow-hidden flex flex-col" wire:key="site-preview"
     data-olx-origin="{{ $clientOrigin }}"
     x-init="document.body.classList.add('olx-edit-fullscreen')"
     x-on:livewire:navigating.window="document.body.classList.remove('olx-edit-fullscreen')"
     x-data="{
        device: window.innerWidth <= 640 ? 'mobile' : (window.innerWidth <= 1024 ? 'tablet' : 'desktop'),
        zoom: 100,
        // The frame always renders the REAL viewport for the chosen device and
        // is scaled to fit the panel — so the preview is pixel-identical to
        // the original site instead of showing a squeezed narrow layout.
        cw: 0, ch: 0,
        // Zooming out widens the rendered viewport (browser-zoom style): the
        // page always fills the frame edge-to-edge and 75/50/25% simply show
        // more of it at once.
        // The frame IS the device screen: desktop renders 1:1 at the frame's
        // own width (exactly like the original project in a window that size);
        // tablet/mobile emulate real device widths. Zooming out renders a
        // proportionally wider viewport scaled down INSIDE the frame — the
        // frame's box never changes, the site just zooms browser-style.
        logicalW() {
            const base = this.device === 'mobile' ? 390 : (this.device === 'tablet' ? 768 : (this.cw || 1024));
            return Math.round(base * 100 / this.zoom);
        },
        frameScale() {
            if (this.device === 'desktop') return this.zoom / 100;
            const fit = this.cw ? Math.min(1, this.cw / (this.device === 'mobile' ? 390 : 768)) : 1;
            return fit * this.zoom / 100;
        },
        frameStyle() {
            if (this.device === 'desktop' && this.zoom === 100) return 'border:0; width:100%; height:100%';
            const w = this.logicalW(), s = this.frameScale() || 1, h = (this.ch || 600) / s;
            return `border:0; width:${w}px; height:${h}px; transform:scale(${s}); transform-origin: top center; position:relative; left:50%; margin-left:-${w / 2}px`;
        },
        init() {
            // Client iframe → CMS: a component was clicked in edit mode.
            // Trust ONLY the configured client site's origin — any other frame
            // could postMessage forged edits through the editor's session.
            window.addEventListener('message', (e) => {
                if (e.origin !== this.$root.dataset.olxOrigin) return;
                const d = e.data;
                if (!d || d.source !== 'olx-connect') return;
                if (d.type === 'olx-edit-select') this.$wire.onEditSelect(d.id, d.key, d.kind, d.itemIndex ?? null, d.itemText ?? null)
                    .then(() => {
                        document.getElementById('olx-frame')?.contentWindow?.postMessage({ source: 'olx-cms', type: 'olx-edit-opened' }, '*');
                        // A clicked FIELD lights up its input in the freshly loaded panel.
                        if (d.field) this.$nextTick(() => this.hotNode(d.field));
                    });
                if (d.type === 'olx-field-edit') this.$wire.inlineFieldEdit(d.id, d.key, d.kind, d.field, d.value, d.itemId);
                if (d.type === 'olx-item-remove') this.$wire.inlineItemRemove(d.id, d.key, d.itemId);
                if (d.type === 'olx-item-add') this.$wire.inlineItemAdd(d.id, d.key, d.componentKey, d.field);
                if (d.type === 'olx-node-item-add') this.$wire.inlineNodeItemAdd(d.key, d.prefix);
                if (d.type === 'olx-navigate') this.$wire.set('previewPath', d.path || '/');
                if (d.type === 'olx-item-remove-idx') this.$wire.inlineItemRemoveByIndex(d.key, d.index);
                if (d.type === 'olx-field-edit-idx') this.$wire.inlineFieldEditByIndex(d.key, d.field, d.value, d.index);
                if (d.type === 'olx-link-edit') this.$wire.inlineLinkEdit(d.key, d.kind, d.labelField ?? '', d.label, d.href, d.index ?? null, d.oldLabel ?? '', d.oldHref ?? '');
                if (d.type === 'olx-register') { this.$wire.registerMarkers(d.markers); this.sendTheme(); }
                // The preview re-reads collections on olux:refresh — update them in place too.
                if (d.type === 'olx-live-collections' && ! this.$wire.liveCollections) this.$wire.set('liveCollections', true, false);
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
        // Hand the admin theme colour to the edit agent so preview highlights
        // (transparent overlay, rings, name tag) match the edit panel.
        sendTheme() {
            const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
            if (!primary) return;
            document.getElementById('olx-frame')?.contentWindow
                ?.postMessage({ source: 'olx-cms', type: 'olx-theme', primary }, '*');
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
        focusEditor(target, index) {
            this.$nextTick(() => {
                const panel = document.getElementById('olx-inspector');
                if (!panel) return;
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                const flash = el => { if (!el) return; el.classList.remove('olx-flash'); void el.offsetWidth; el.classList.add('olx-flash'); };
                const activate = el => {
                    panel.querySelectorAll('[data-item-row].olx-active').forEach(r => r.classList.remove('olx-active'));
                    if (el) el.classList.add('olx-active');
                };
                const rows = panel.querySelectorAll('[data-item-row]');
                if (target === 'item' && rows[index] !== undefined) {
                    rows[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    rows[index].dispatchEvent(new CustomEvent('olx-expand', { bubbles: false }));
                    activate(rows[index]);
                    flash(rows[index]);
                } else if (target === 'last-item' && rows.length) {
                    rows[rows.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    rows[rows.length - 1].dispatchEvent(new CustomEvent('olx-expand', { bubbles: false }));
                    activate(rows[rows.length - 1]);
                    flash(rows[rows.length - 1]);
                } else if (target === 'items') {
                    const list = panel.querySelector('[data-items-list]') || panel;
                    list.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    flash(list);
                } else { panel.scrollTop = 0; }
            });
        },
     }"
     x-on:olx-editor-focus.window="focusEditor($event.detail?.target, $event.detail?.index)"
     {{-- .window, not a plain @click: animations.css presses ANY [\@click]
          element on :active — a bare @click here made the WHOLE page scale
          down like a pushed button on every click. --}}
     @click.window="const row = $event.target.closest?.('[data-item-row]'); if (row && $event.target.closest('#olx-inspector')) { document.querySelectorAll('[data-item-row].olx-active').forEach(r => r !== row && r.classList.remove('olx-active')); row.classList.add('olx-active'); }">
{{-- Mobile: the edit page is FULL SCREEN — the app header, breadcrumb bar and
     notice banners hide so the preview + editor own the whole viewport. --}}
<style>
@media (max-width: 1023.98px) {
    body.olx-edit-fullscreen header.shrink-0 { display: none; }
    body.olx-edit-fullscreen div:has(> nav[aria-label="Breadcrumb"]) { display: none; }
    body.olx-edit-fullscreen #MainBody div.bg-amber-50 { display: none; }
    body.olx-edit-fullscreen { height: 100dvh; overflow: hidden; }
    body.olx-edit-fullscreen div.flex-1.min-h-0.flex.overflow-hidden { height: 100%; }
    body.olx-edit-fullscreen #MainBody { height: 100%; overflow: hidden; }
    body.olx-edit-fullscreen #MainBody .h-full > div[x-show] { height: 100%; }
    body.olx-edit-fullscreen [wire\:key="site-preview"] { height: 100%; overflow: hidden; }
}
</style>

    @include('livewire.partials.olx-rich-assets')
    <style>
        /* Baseline frame size in CSS, not only in the Alpine :style — every
           Livewire morph resets the style attribute to the server-rendered one
           for a frame, and a styleless iframe collapses to 300×150: the whole
           preview visibly shrank on EVERY click, shifting the panel under the
           cursor so buttons missed their clicks. */
        #olx-frame { border:0; width:100%; height:100%; display:block; }
        /* Slim scrollbar for the edit panel column — dark-gray thumb on a
           light track so it reads clearly against the page. */
        .olx-scroll { scrollbar-width: thin; scrollbar-color: #4b5563 rgba(0,0,0,.1); }
        .olx-scroll::-webkit-scrollbar { width: 9px; }
        .olx-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,.1); border-radius: 8px; }
        .dark .olx-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.1); }
        .olx-scroll::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 8px;
            border: 1.5px solid rgba(255,255,255,.55); }
        .dark .olx-scroll::-webkit-scrollbar-thumb { background: #6b7280; border-color: rgba(0,0,0,.4); }
        .olx-scroll::-webkit-scrollbar-thumb:hover { background: #1f2937; }
        .olx-save { margin-top:.75rem; width:100%; padding:.5rem; border-radius:12px; font-weight:700;
                    font-size:13px; color:#fff; background:var(--primary); }
        /* Mini rich-text editor (zero-dependency contenteditable) */
        /* Readability: the editor panel runs larger than the utility classes
           sprinkled through its partials — override them here in one place
           instead of retouching every text-[10px]/text-xs in the blades. */
        #olx-inspector .olx-in,
        #olx-inspector .olx-rt-area,
        #olx-inspector textarea,
        #olx-inspector input,
        #olx-inspector select { font-size:14px; }
        #olx-inspector .olx-card { font-size:13.5px; }
        #olx-inspector .text-xs { font-size:13.5px; line-height:1.45; }
        #olx-inspector .text-sm { font-size:14.5px; }
        #olx-inspector [class*="text-[10px]"],
        #olx-inspector [class*="text-[11px]"] { font-size:12.5px; }
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
        /* The entry being worked on stays visibly selected */
        [data-item-row].olx-active { box-shadow: 0 0 0 2px var(--primary); border-color: transparent !important; }
        [data-item-row].olx-active > div:first-child { background: color-mix(in srgb, var(--primary) 7%, transparent); }
        /* One-shot attention flash after item add/remove */
        .olx-flash { animation: olxflash 1.2s ease; border-radius:10px; }
        @keyframes olxflash { 0% { background: rgba(99,102,241,.22); box-shadow: 0 0 0 2px rgba(99,102,241,.55); }
                              100% { background: transparent; box-shadow: none; } }
    </style>

    {{-- Toolbar — hidden on mobile when embedded in the page Content tab --}}
    <div class="{{ $embedded ? 'hidden lg:flex' : 'flex' }} items-center gap-3 mb-3 flex-wrap">
        {{-- Mobile exit: the page is full screen (app chrome hidden) — this
             is the only way back to the rest of the admin. --}}
        <a href="{{ url($site->name.'/dashboard') }}" wire:navigate aria-label="Exit edit mode"
           class="lg:hidden shrink-0 w-9 h-9 grid place-items-center rounded-full bg-white dark:bg-white/[0.08] border border-gray-200 dark:border-white/[0.1] text-gray-600 dark:text-gray-200 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-lg font-extrabold text-gray-900 dark:text-white">Edit mode</h1>
        @if ($livePreviewUrl)
            <x-preview-button :href="$livePreviewUrl" small title="Open this page exactly as visitors see it — no edit chrome" />
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

        <span class="hidden lg:inline text-xs text-gray-400">Click a component in the live preview to edit it.</span>

        {{-- Device preview: resizes the iframe to phone / tablet / full width.
             Pointless on a phone (the preview is already phone-width) — desktop only. --}}
        <div class="hidden lg:flex items-center gap-1 rounded-xl border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.04] p-1 shadow-sm">
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

        {{-- Zoom: scale the preview down to see more of the page at once (desktop only) --}}
        <div class="hidden lg:flex items-center gap-1 rounded-xl border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.04] p-1 shadow-sm" title="Preview zoom">
            @foreach ([100, 75, 50, 25] as $z)
                <button type="button" @click="zoom = {{ $z }}"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors"
                        :class="zoom === {{ $z }} ? 'text-white' : 'text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.08]'"
                        :style="zoom === {{ $z }} ? 'background:var(--primary)' : ''">{{ $z }}%</button>
            @endforeach
        </div>

        @unless($embedded)
        <!-- <div class="ml-auto flex items-center gap-2">
            <button wire:click="publish" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.1]">Publish page.json</button>
            <a href="{{ route('site.connect.export', ['siteID' => $site->name]) }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.1]">Download export</a>
        </div> -->
        @endunless
        @if ($flash)
            <span class="w-full text-xs font-semibold" style="color:var(--primary)">{{ $flash }}</span>
        @endif
    </div>


    @if ($this->installStatus === 'installing')
        {{-- The design was just applied; pages, forms and modules are being
             created by a background job. Poll until it flips to done/failed —
             the poll lives only in this branch, so it stops automatically. --}}
        @php
            $ip = $this->installProgress;
            $steps = ['pages' => 'Pages & sections', 'layout' => 'Header & footer', 'forms' => 'Forms', 'collections' => 'Collections', 'theme' => 'Colours & fonts'];
            $order = ['start', 'pages', 'layout', 'features', 'forms', 'collections', 'linking', 'booking', 'theme', 'done'];
            $at = array_search($ip['step'], $order, true);
        @endphp
        <div wire:poll.1s class="flex-1 grid place-items-center border border-dashed rounded-2xl px-6 text-center">
            <div class="w-full max-w-md space-y-4" role="status" aria-live="polite">
                <p class="text-base font-bold text-gray-800 dark:text-gray-100">Setting up your site…</p>
                {{-- Progress bar --}}
                <div>
                    <div class="h-3 w-full rounded-full bg-gray-100 dark:bg-white/[0.08] overflow-hidden" aria-hidden="true">
                        <div class="h-full rounded-full transition-all duration-700 ease-out" style="width:{{ max(3, (int) $ip['percent']) }}%;background:var(--primary)"></div>
                    </div>
                    <div class="flex items-center justify-between mt-1.5 text-[12px]">
                        <span class="text-gray-600 dark:text-gray-300 truncate">{{ $ip['label'] }}@if ($ip['total']) <span class="text-gray-400">· {{ $ip['done'] }} of {{ $ip['total'] }}</span>@endif</span>
                        <span class="font-bold text-gray-800 dark:text-gray-100 tabular-nums">{{ (int) $ip['percent'] }}%</span>
                    </div>
                </div>
                {{-- Step checklist --}}
                <ul class="text-left inline-flex flex-col gap-1.5 text-[12.5px]">
                    @foreach ($steps as $sk => $sl)
                        @php $si = array_search($sk, $order, true); $state = $at === false ? 'todo' : ($si < $at ? 'done' : ($si === $at ? 'now' : 'todo')); @endphp
                        <li class="flex items-center gap-2 {{ $state === 'todo' ? 'text-gray-400' : 'text-gray-700 dark:text-gray-200' }}">
                            @if ($state === 'done')
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[10px] grid place-items-center">✓</span>
                            @elseif ($state === 'now')
                                <span class="w-4 h-4 rounded-full border-2 border-t-transparent animate-spin" style="border-color:var(--primary);border-top-color:transparent"></span>
                            @else
                                <span class="w-4 h-4 rounded-full border-2 border-gray-200 dark:border-white/[0.15]"></span>
                            @endif
                            {{ $sl }}
                        </li>
                    @endforeach
                </ul>
                <p class="text-[11.5px] text-gray-400">A fresh copy of the template: its pages, sections and sample text — none of its data. The preview appears here automatically.</p>
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
            No preview available yet. Apply a design from <a href="{{ url($site->name.'/designs') }}" class="font-semibold text-indigo-500 hover:underline">My Designs</a> and your site shows here with live content.
        </div>
    @else
        @if ($embedded)
        {{-- Embedded (page-detail Content tab): compact two-pane layout --}}
        <div class="flex-1 grid {{ $selectedKind ? 'lg:grid-cols-[1fr_360px]' : '' }} gap-4 min-h-0">
            {{-- Live client site (edit mode); width follows the device toggle --}}
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                 :class="device === 'desktop' ? 'bg-white' : 'bg-gray-100 dark:bg-black/30'">
                <div class="h-full w-full bg-white overflow-hidden"
                     x-init="const sync = () => { cw = $el.clientWidth; ch = $el.clientHeight }; new ResizeObserver(sync).observe($el); sync()">
                    {{-- Renders at the device's REAL viewport width, scaled to fit
                         (× the zoom choice) — sharp and fully interactive. --}}
                    <iframe id="olx-frame" src="{{ $embedUrl }}"
                            :style="frameStyle()"></iframe>
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
                <div class="flex-1 overflow-y-auto p-4 olx-scroll">
                    <div wire:loading.flex wire:target="onEditSelect,select" class="min-h-[60vh] flex-col items-center justify-center gap-3 text-sm font-bold" style="color:var(--primary)">
                        <svg class="animate-spin h-9 w-9" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Loading section content…
                    </div>
                    <div wire:loading.remove wire:target="onEditSelect,select">
                        @include('livewire.partials.connect-inspector')
                    </div>
                </div>
            </div>
            @endif
        </div>
        @else
        {{-- Edit mode is a 3-slide layout: pages | live preview | editor.
             Mobile swipes between the slides; desktop shows all three. --}}
        <x-carousel :labels="['📄 Pages', '🖥 Preview', '✏️ Edit']" :start="1">

        {{-- ════ LEFT: pages ════ --}}
        <x-carousel.slide class="lg:!w-[270px] lg:shrink-0 pb-24 lg:pb-2 max-h-full overflow-y-auto lg:h-full lg:max-h-full lg:overflow-y-auto no-scrollbar">
            {{-- The card scrolls its own list (auto overflow) so the long
                 pages + detail-pages index never stretches the layout --}}
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] p-2">
                @if ($site->allows(auth()->user(), 'properties.manage'))
                    @php $spOpen = ! empty($edit['siteProperties']) && $mode === 'edit'; @endphp
                    <button wire:click="openSiteProperties"
                            class="w-full text-left mb-2 px-2.5 py-2 rounded-xl text-xs font-semibold transition-colors border border-dashed
                                   {{ $spOpen ? 'text-white border-transparent' : 'text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/[0.05]' }}"
                            @if ($spOpen) style="background:var(--primary)" @endif>
                        ⚙️ Site properties
                        <span class="block text-[10px] font-normal {{ $spOpen ? 'text-white/75' : 'text-gray-400' }}">name, logo, contact, hours, SEO…</span>
                    </button>
                @endif
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

                {{-- Detail pages: routes that render one entry by id (profiles,
                     studies, sermons…) — pulled from each route's data source --}}
                @if ($this->dynamicPages !== [])
                    <p class="px-2 pt-3 pb-1.5 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 border-t border-gray-50 dark:border-white/[0.04] mt-2">Detail pages</p>
                    @foreach ($this->dynamicPages as $group => $dpRows)
                        <div x-data="{ open: false }">
                            <button type="button" @click="open = ! open"
                                    class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl text-xs font-semibold text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/[0.05]">
                                <span>{{ $group }} <span class="font-normal text-gray-300 dark:text-gray-500">({{ count($dpRows) }})</span></span>
                                <svg class="w-3 h-3 opacity-50 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-collapse x-cloak>
                                @foreach ($dpRows as $row)
                                    <button wire:click="$set('previewPath', '{{ $row['url'] }}')"
                                            class="w-full text-left pl-5 pr-2.5 py-1.5 rounded-xl text-xs transition-colors
                                                   {{ $previewPath === $row['url']
                                                       ? 'text-white font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.05]' }}"
                                            @if($previewPath === $row['url']) style="background:var(--primary)" @endif>
                                        {{ $row['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </x-carousel.slide>

        {{-- ════ MIDDLE: live preview ════ --}}
        <x-carousel.slide class="lg:flex-1 lg:min-w-0 h-full pb-0 lg:pb-2 max-h-full overflow-hidden lg:h-full lg:max-h-full lg:overflow-hidden no-scrollbar">
            <div class="h-full flex flex-col">
            {{-- Live client site (edit mode); width follows the device toggle.
                 flex-1 + min-h-0: fill the fixed-height wrapper — without it
                 the frame collapses to a strip. --}}
            <div class="flex-1 min-h-0 rounded-none border-0 lg:rounded-2xl lg:border lg:border-gray-100 dark:lg:border-white/[0.06] overflow-hidden"
                 :class="device === 'desktop' ? 'bg-white' : 'bg-gray-100 dark:bg-black/30'">
                <div class="h-full w-full bg-white overflow-hidden"
                     x-init="const sync = () => { cw = $el.clientWidth; ch = $el.clientHeight }; new ResizeObserver(sync).observe($el); sync()">
                    {{-- Renders at the device's REAL viewport width, scaled to fit
                         (× the zoom choice) — sharp and fully interactive. --}}
                    <iframe id="olx-frame" src="{{ $embedUrl }}"
                            :style="frameStyle()"></iframe>
                </div>
            </div>
            </div>
        </x-carousel.slide>

        {{-- ════ RIGHT: content editor ════ --}}
        {{-- pb-44 on mobile: the fixed pane-switcher pill + Save bar stack
             ~140px over the bottom — less padding leaves the last field
             stuck underneath them. --}}
        {{-- olx-scroll (not no-scrollbar): the edit panel is a long form —
             a visible slim scrollbar shows where you are and what's left. --}}
        <x-carousel.slide class="lg:!w-[410px] lg:shrink-0 pb-44 lg:pb-2 max-h-full overflow-y-auto lg:h-full lg:max-h-full lg:overflow-y-auto olx-scroll">
            {{-- NO fixed height / flex sizing on this card: capping it made the
                 content child overflow past the white background (unclipped
                 cards floating on the page). The loader "fills" via min-h. --}}
            <div id="olx-inspector" class="rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] p-4">
                {{-- Click select in flight → the panel shows a tall centered loader --}}
                <div wire:loading.flex wire:target="onEditSelect,select" class="min-h-[60vh] flex-col items-center justify-center gap-3 text-sm font-bold" style="color:var(--primary)">
                    <svg class="animate-spin h-9 w-9" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Loading section content…
                </div>
                <div wire:loading.remove wire:target="onEditSelect,select">
                    @if (! $selectedKind)
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">✏️ Edit content</p>
                        <p class="mt-2 text-sm text-gray-400">Click any section in the preview — it outlines and its content opens here to edit.</p>
                    @else
                        @include('livewire.partials.connect-inspector')
                    @endif
                </div>
            </div>
        </x-carousel.slide>
        </x-carousel>
        @endif
    @endif

    {{-- Asset library modal — opened by the "Assets" button on image fields;
         selection returns a portable @media ref via the media-picked event. --}}
    <livewire:media-picker :site-id="$site->id" :key="'connect-media-picker-'.$site->id" />

</div>
