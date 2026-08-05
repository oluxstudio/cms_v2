@php
    $stats = $this->stats;
    $canManage = $site->canManageTeam(auth()->user());
@endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Posts</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Write, publish and see which posts your visitors love.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search posts…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-56">
            </div>
            @if($canManage)
            <button wire:click="createPost"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create post
            </button>
            @endif
        </div>
    </div>

    {{-- Insight tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-tile label="Posts written" :value="number_format($stats['total'])" :sub="$stats['published'].' published'" accent="ink" />
        <x-tile label="Drafts" :value="number_format($stats['total'] - $stats['published'])" sub="awaiting publish" accent="cocoa" />
        <x-tile label="Total visits" :value="number_format($stats['views'])" sub="across all posts" accent="lime" />
        <x-tile label="Engagement" :value="number_format($stats['engagement'])" sub="likes + comments" accent="lavender" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-6">

        {{-- ── All posts (paginated) ── --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden self-start">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">All posts</h2>
            </div>
            @forelse($posts as $post)
            <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                <div class="w-11 h-11 rounded-xl shrink-0 overflow-hidden bg-gray-100 dark:bg-white/[0.05] grid place-items-center text-lg">
                    @if($post->cover_image)
                        <img src="{{ $post->cover_image }}" alt="" class="w-full h-full object-cover" loading="lazy">
                    @else 📝 @endif
                </div>
                <div class="min-w-0 flex-1 {{ $canManage ? 'cursor-pointer' : '' }}" @if($canManage) wire:click="editPost({{ $post->id }})" @endif>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $post->title }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                        {{ $post->author?->name ?? 'Unknown' }} · {{ ($post->published_at ?? $post->created_at)->format('M j, Y') }}
                        · 👁 {{ number_format($post->views) }} · ❤ {{ number_format($post->likes) }} · 💬 {{ number_format($post->comments) }}
                    </p>
                </div>
                <button @if($canManage) @click.stop="$wire.togglePublish({{ $post->id }})" @endif
                        class="shrink-0 text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $canManage ? 'cursor-pointer' : 'cursor-default' }}
                        {{ $post->isPublished()
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400' }}"
                        title="{{ $canManage ? 'Click to toggle publish' : '' }}">
                    {{ $post->isPublished() ? 'Published' : 'Draft' }}
                </button>
                @if($canManage)
                <button type="button" @click.stop="if (confirm('Delete this post?')) $wire.deletePost({{ $post->id }})"
                        class="p-1.5 rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                @endif
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <span class="text-3xl mb-3">📝</span>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ $search !== '' ? 'No posts match your search.' : 'No posts yet.' }}</p>
                @if($canManage && $search === '')
                    <button wire:click="createPost" class="mt-3 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Write your first post →</button>
                @endif
            </div>
            @endforelse

            @if($posts->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 dark:border-white/[0.05]">{{ $posts->links() }}</div>
            @endif
        </div>

        {{-- ── Top-10 rankings ── --}}
        <div class="space-y-6">
            @foreach([
                ['Top 10 by visits', $this->topByViews, fn ($p) => '👁 '.number_format($p->views)],
                ['Top 10 by engagement', $this->topByEngagement, fn ($p) => '❤ '.number_format($p->likes).' · 💬 '.number_format($p->comments)],
            ] as [$heading, $list, $metric])
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $heading }}</h2>
                </div>
                @forelse($list as $i => $p)
                <div class="flex items-center gap-3 px-5 py-2.5 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                    <span class="w-6 h-6 rounded-lg grid place-items-center text-[11px] font-bold shrink-0
                        {{ $i < 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.05] text-gray-500 dark:text-gray-400' }}">{{ $i + 1 }}</span>
                    <p class="text-[13px] font-medium text-gray-800 dark:text-gray-200 truncate flex-1">{{ $p->title }}</p>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums shrink-0">{{ $metric($p) }}</span>
                </div>
                @empty
                <p class="px-5 py-6 text-xs text-gray-400 text-center">Nothing to rank yet.</p>
                @endforelse
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Create / edit modal ── --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="$set('showForm', false)"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $editingId ? 'Edit post' : 'Create post' }}</h2>
                <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="savePost" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Title</label>
                    <input wire:model="title" type="text" placeholder="A headline readers can't skip…"
                           class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Excerpt <span class="font-normal text-gray-400">(shown in lists)</span></label>
                    <textarea wire:model="excerpt" rows="2"
                              class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"></textarea>
                </div>
                <div wire:ignore>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">
                        Body <span class="font-normal text-gray-400">— headings, paragraphs, lists · insert images &amp; video from your Media library</span>
                    </label>
                    @include('partials.post-body-editor', ['mediaAssets' => $this->mediaAssets])
                </div>
                <div class="grid sm:grid-cols-[1fr_auto] gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Cover image URL <a href="{{ url($site->name.'/media') }}" class="font-normal text-indigo-500 hover:underline">(Media)</a></label>
                        <input wire:model="coverImage" type="text" placeholder="https://… or /media/…"
                               class="w-full text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                        <select wire:model="status"
                                class="text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] px-3.5 py-2.5 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showForm', false)"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08] hover:border-gray-300 transition-colors">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                        {{ $editingId ? 'Save changes' : 'Create post' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endassets

@script
<script>
    /**
     * Quill-backed post editor + Media-library lightbox. Quill owns ALL
     * selection/formatting behaviour — the only custom parts are legacy body
     * conversion and inserting library assets at the remembered cursor.
     */
    window.postQuill = function ($wire, assets = []) {
        const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        const gridToHtml = (d) => {
            const blockHtml = (b) => ({
                h1: `<h1>${esc(b.text)}</h1>`, h2: `<h2>${esc(b.text)}</h2>`, h3: `<h3>${esc(b.text)}</h3>`,
                p: `<p>${esc(b.text)}</p>`,
                image: b.url ? `<img src="${esc(b.url)}" alt="${esc(b.alt)}">` : '',
                video: b.url ? `<p><a href="${esc(b.url)}">${esc(b.url)}</a></p>` : '',
                ul: `<ul>${(b.items || []).map(i => `<li>${esc(i)}</li>`).join('')}</ul>`,
                ol: `<ol>${(b.items || []).map(i => `<li>${esc(i)}</li>`).join('')}</ol>`,
            })[b.type] || '';
            return d.rows.flatMap(r => r.cells.flatMap(c => c.blocks.map(blockHtml))).join('');
        };

        const initialHtml = (raw) => {
            const s = (raw || '').trim();
            if (s === '') return '';
            try {
                const d = JSON.parse(s);
                if (d && d.format === 'grid-v1') return gridToHtml(d);
            } catch (_) { /* not JSON */ }
            if (s.startsWith('<')) return s;
            return s.split(/\n{2,}/).map(p => `<p>${esc(p).replace(/\n/g, '<br>')}</p>`).join('');
        };

        return {
            picker: false,
            pickerSearch: '',
            assets,
            savedIndex: null,

            // The Quill instance lives on the DOM node, NOT in Alpine state —
            // Alpine's reactive Proxy breaks Quill's internal registry lookups
            // (every API call dies with "null.offset").
            quill() { return this.$refs.editor.__quill; },

            init() {
                if (!window.Quill) { setTimeout(() => this.init(), 100); return; } // CDN still loading
                const q = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: 'Start with a heading, then tell the story…',
                    modules: {
                        table: true,
                        toolbar: {
                            container: [
                                [{ header: 1 }, { header: 2 }, { header: 3 }, { header: 4 }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['blockquote', 'link'],
                                ['image', 'video'],
                                ['clean'],
                            ],
                            handlers: {
                                // Our Media-library lightbox instead of Quill's file dialog.
                                image: () => this.openPicker(),
                            },
                        },
                    },
                });
                this.$refs.editor.__quill = q;
                const html = initialHtml($wire.body);
                if (html) q.clipboard.dangerouslyPasteHTML(html, 'silent');
                $wire.body = q.root.innerHTML;
                q.on('text-change', () => { $wire.body = q.root.innerHTML; });

                // ── Bullet-proof inline toggles (B/I/U/S) ─────────────────────
                // Some environments (extensions, focus quirks) collapse the native
                // selection during a toolbar click, so inline formats see nothing
                // selected. We track Quill's own {index,length} range — via its
                // selection events AND a capture-phase mousedown snapshot — and
                // format BY COORDINATES with formatText(), which never consults
                // the native selection.
                const el = this.$refs.editor;
                q.on('selection-change', (range) => { if (range && range.length > 0) el.__lastRange = range; });
                const tbEl = el.parentElement.querySelector('.ql-toolbar');
                if (tbEl) tbEl.addEventListener('mousedown', () => {
                    const r = q.getSelection();
                    if (r && r.length > 0) el.__lastRange = r;
                }, true);

                // ── Tables: insert/row/column controls + per-table styling toggles ──
                // Styling = classes ON the <table> element (striped rows/columns,
                // bold header/footer). They ride along in the saved HTML and are
                // re-applied after Quill re-parses the body on edit (see below).
                const tblM = q.getModule('table');
                const currentTable = () => {
                    const sel = q.getSelection();
                    if (sel) {
                        const [t] = tblM.getTable(sel);
                        if (t) return t.domNode;
                    }
                    const all = q.root.querySelectorAll('table');
                    return all.length ? all[all.length - 1] : null;
                };
                const syncBody = () => { $wire.body = q.root.innerHTML; };
                const tblOp = (fn) => () => { if (q.getSelection() && tblM.getTable(q.getSelection())[0]) { fn(); syncBody(); } };
                const tblToggle = (cls) => () => { const t = currentTable(); if (t) { t.classList.toggle(cls); syncBody(); } };

                if (tbEl) {
                    const grp = document.createElement('span');
                    grp.className = 'ql-formats pbe-tblgrp';
                    const mk = (label, title, fn) => {
                        const btn = document.createElement('button');
                        btn.type = 'button'; btn.textContent = label; btn.title = title; btn.className = 'pbe-tbtn';
                        btn.addEventListener('click', fn);
                        grp.appendChild(btn);
                    };
                    mk('⊞', 'Insert a 3×3 table', () => {
                        const sel = q.getSelection(true) || { index: Math.max(0, q.getLength() - 1) };
                        if (tblM.getTable(sel)[0]) return; // no tables inside tables
                        tblM.insertTable(3, 3);
                        const t = currentTable();
                        if (t) t.classList.add('pbe-t-head'); // formatted by default: bold header
                        syncBody();
                    });
                    mk('+⇣', 'Add a row below', tblOp(() => tblM.insertRowBelow()));
                    mk('+⇢', 'Add a column right', tblOp(() => tblM.insertColumnRight()));
                    mk('−R', 'Delete this row', tblOp(() => tblM.deleteRow()));
                    mk('−C', 'Delete this column', tblOp(() => tblM.deleteColumn()));
                    mk('▤', 'Toggle striped ROWS (alternate background)', tblToggle('pbe-t-rows'));
                    mk('▥', 'Toggle striped COLUMNS (alternate background)', tblToggle('pbe-t-cols'));
                    mk('𝐇', 'Toggle bold header row', tblToggle('pbe-t-head'));
                    mk('𝐅', 'Toggle bold footer row', tblToggle('pbe-t-foot'));
                    mk('✕⊞', 'Delete the whole table', tblOp(() => tblM.deleteTable()));
                    tbEl.appendChild(grp);
                }

                // Re-apply table styling classes lost when Quill re-parses the body.
                const restoreTableClasses = (fromHtml) => {
                    const src = [...fromHtml.matchAll(/<table[^>]*class="([^"]*)"/g)].map(m => m[1]);
                    if (!src.length) return;
                    q.root.querySelectorAll('table').forEach((t, i) => {
                        (src[i] || '').split(/\s+/).filter(c => c.startsWith('pbe-t-')).forEach(c => t.classList.add(c));
                    });
                    syncBody();
                };
                if (html) restoreTableClasses(html);

                const tb = q.getModule('toolbar');
                ['bold', 'italic', 'underline', 'strike'].forEach((fmt) => {
                    tb.addHandler(fmt, () => {
                        let r = q.getSelection();
                        if (!r || r.length === 0) r = el.__lastRange || r;
                        if (!r) { q.focus(); return; }
                        if (r.length === 0) {
                            // No selection anywhere: toggle for upcoming typing.
                            q.format(fmt, !(q.getFormat(r.index) || {})[fmt], 'user');
                            return;
                        }
                        const on = !!(q.getFormat(r.index, r.length) || {})[fmt];
                        q.formatText(r.index, r.length, fmt, !on, 'user');
                        try { q.setSelection(r.index, r.length, 'silent'); } catch (_) {}
                        el.__lastRange = { index: r.index, length: r.length };
                    });
                });
            },

            openPicker() {
                const q = this.quill();
                let sel = null;
                try { sel = q.getSelection(); } catch (_) { /* unfocused editor */ }
                this.savedIndex = sel ? sel.index : Math.max(0, q.getLength() - 1);
                this.pickerSearch = '';
                this.picker = true;
            },
            filteredAssets() {
                const q = this.pickerSearch.toLowerCase().trim();
                return q ? this.assets.filter(a => (a.name || '').toLowerCase().includes(q)) : this.assets;
            },
            insertAsset(a) {
                this.picker = false;
                const q = this.quill();
                const i = this.savedIndex ?? Math.max(0, q.getLength() - 1);
                q.insertEmbed(i, a.type === 'image' ? 'image' : 'video', a.url, 'api');
                try { q.setSelection(i + 1, 0, 'silent'); } catch (_) {}
                $wire.body = q.root.innerHTML;
            },
        };
    };
</script>
@endscript
