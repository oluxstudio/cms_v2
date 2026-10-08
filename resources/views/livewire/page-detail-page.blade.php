@php
    $s = $this->summary;
    $seo = $this->seoChecks;
    $seoOk = collect($seo)->where(1, true)->count();
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $trend = $s['visits_prev'] > 0 ? (int) round(($s['visits_30d'] - $s['visits_prev']) / $s['visits_prev'] * 100) : null;
    $metaTitleShown = trim($metaTitle) !== '' ? $metaTitle : $page->name.' — '.($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
    $previewHref = $site->previewUrl($page->url);
@endphp
{{-- One page's admin screen on the house 3-pane layout: stats · settings · summary --}}
<div class="min-h-full flex flex-col"
     x-data="{ toast:'' }"
     x-init="$watch('$wire.successMessage', v => { if(v){ toast=v; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } })">

<x-tri-layout :title="$page->name" :subtitle="$page->url" :site-name="$site->name"
    :labels="['📊 Overview', '📄 Page', '🔎 Summary']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pages', ['siteID' => $site->name]) }}" wire:navigate class="{{ $btnSolid }} text-[13px] px-3.5 py-2">← All pages</a>
            <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $page->is_published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                {{ $page->is_published ? '● Live' : 'Draft' }}
            </span>
            @if ($previewHref)
                <x-preview-button :href="$previewHref" label="View page" small />
            @endif
            <a href="{{ url($site->name.'/connect?page='.$page->id) }}" title="Open this page in Edit site"
               class="inline-flex items-center gap-1.5 text-[13px] font-bold px-3.5 py-2 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit content
            </a>
        </div>
    </x-slot:header>

    {{-- ── LEFT rail: this page at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$s['components']" :label="Str::plural('Section', $s['components'])"
                :sub="$s['fields'].' '.Str::plural('field', $s['fields']).($s['layout'] ? ' · '.$s['layout'] : '')"
                icon="M4 5a1 1 0 011-1h14a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        <x-tile accent="lime" :value="number_format($s['visits_30d'])" label="Visits · 30 days"
                :sub="$trend === null ? 'this page only' : ($trend >= 0 ? '▲ '.$trend.'% vs previous 30' : '▼ '.abs($trend).'% vs previous 30')"
                icon="M3 13.5L9 7.5l4 4L21 3.5M21 3.5h-5m5 0v5M4 20h16" />
        <x-tile :accent="$seoOk === count($seo) ? 'sky' : ($seoOk <= 2 ? 'rose' : 'cocoa')" :value="$seoOk.'/'.count($seo)" label="SEO checks"
                :sub="$seoOk === count($seo) ? 'all good' : (count($seo) - $seoOk).' to improve'"
                icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        <x-tile accent="lavender" :value="$s['sources']" label="Content sources" sub="collections, posts & products shown here"
                icon="M4 7v10c0 2 3.6 3 8 3s8-1 8-3V7M4 7c0 2 3.6 3 8 3s8-1 8-3M4 7c0-2 3.6-3 8-3s8 1 8 3m0 5c0 2-3.6 3-8 3s-8-1-8-3" />
        <x-tile accent="cocoa" :value="$s['updated']?->diffForHumans(short: true) ?? '—'" label="Last edited"
                :sub="$page->is_published ? 'live on the site' : 'draft — not on the site'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </div>
    </x-slot:rail>

    {{-- ── CENTER: the page's settings, in pill tabs ── --}}
    <div class="space-y-5">
        @if ($s['inactive'])
            <div class="rounded-2xl px-4 py-3 bg-amber-50 dark:bg-amber-500/10 text-[13px] text-amber-800 dark:text-amber-200">
                This page belongs to a template that isn't active — it's parked and not shown on the site. Activate it from the <a href="{{ route('pages', ['siteID' => $site->name]) }}" wire:navigate class="font-bold underline">Pages list</a>.
            </div>
        @endif
        @include('livewire.partials.page-detail-tabs', ['seoOk' => $seoOk, 'seoTotal' => count($seo), 'sources' => $s['sources']])

    @if($tab === 'sources')
    {{-- ── Sources tab: what dynamic content this page shows ── --}}
    <form wire:submit="saveSources" class="space-y-4">
        @if($site->hasFeature('store'))
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
            <label class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white cursor-pointer">
                <input wire:model.live="srcProducts.enabled" type="checkbox" class="rounded"> 🛍️ Products on this page
            </label>
            <p class="text-xs text-gray-400 mt-1">The shop section shows exactly this set instead of every product.</p>
            @if($srcProducts['enabled'])
            <div class="grid sm:grid-cols-3 gap-3 mt-3">
                <div>
                    <label class="bkf-label">Category</label>
                    <select wire:model="srcProducts.category" class="bkf-input">
                        <option value="">All categories</option>
                        @foreach($this->productCategories as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="bkf-label">Tags <span class="font-normal opacity-60">comma separated</span></label>
                    <input wire:model="srcProducts.tags" placeholder="repair, bleach-care" class="bkf-input">
                </div>
                <div>
                    <label class="bkf-label">Max products</label>
                    <input wire:model="srcProducts.limit" type="number" min="1" max="50" class="bkf-input">
                </div>
            </div>
            @endif
        </div>
        @endif

        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
            <label class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white cursor-pointer">
                <input wire:model.live="srcPosts.enabled" type="checkbox" class="rounded"> 📰 Posts on this page
            </label>
            <p class="text-xs text-gray-400 mt-1">The blog section shows this filtered set instead of the newest posts.</p>
            @if($srcPosts['enabled'])
            <div class="grid sm:grid-cols-3 gap-3 mt-3">
                <div>
                    <label class="bkf-label">Category</label>
                    <select wire:model="srcPosts.category" class="bkf-input">
                        <option value="">All categories</option>
                        @foreach($this->postCategories as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="bkf-label">Tag</label>
                    <input wire:model="srcPosts.tag" placeholder="e.g. tips" class="bkf-input">
                </div>
                <div>
                    <label class="bkf-label">Max posts</label>
                    <input wire:model="srcPosts.limit" type="number" min="1" max="50" class="bkf-input">
                </div>
            </div>
            @endif
        </div>

        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
            <p class="text-sm font-bold text-gray-900 dark:text-white">🗂 Collections on this page</p>
            <p class="text-xs text-gray-400 mt-1 mb-3">Tick a collection to show it on this page; set a limit to cap how many items appear.</p>
            <input wire:model.live.debounce.300ms="collectionSearch" placeholder="Search collections…"
                   class="w-full mb-3 px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
            <div class="space-y-1.5 max-h-64 overflow-y-auto">
                @forelse($this->sourceCollections as $col)
                <div class="flex items-center gap-3 px-3 py-2 rounded-xl bg-gray-50 dark:bg-white/[0.03]" wire:key="src-col-{{ $col->id }}">
                    <label class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer">
                        <input wire:model.live="srcCollections.{{ $col->id }}.attached" type="checkbox" class="rounded">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $col->name }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $col->items_count }} items</span>
                    </label>
                    @if($srcCollections[$col->id]['attached'] ?? false)
                        <input wire:model="srcCollections.{{ $col->id }}.limit" type="number" min="1" max="50" placeholder="all"
                               class="w-16 px-2 py-1 rounded-lg text-xs bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08]" title="Max items shown">
                    @endif
                </div>
                @empty
                <p class="text-sm text-gray-400 py-4 text-center">No collections yet.</p>
                @endforelse
            </div>
        </div>

        <p class="text-xs text-gray-400">Sections (components) are managed in the <b>Content</b> tab and the component picker on the Pages list.</p>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
            <span wire:loading.remove wire:target="saveSources">Save content sources</span>
            <span wire:loading wire:target="saveSources">Saving…</span>
        </button>
    </form>

    @elseif($tab === 'edit')
    {{-- ── Edit tab ── --}}
    <form wire:submit="saveEdit" class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-6 space-y-4">
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Page name</label>
            <input wire:model="name" type="text" class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">URL path</label>
            <input wire:model="url" type="text" placeholder="/about" class="w-full px-3.5 py-2.5 rounded-xl text-sm font-mono bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
            @error('url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input wire:model="isPublished" type="checkbox" class="rounded"> Published — visible on the site
        </label>
        <p class="text-xs text-gray-400">Content and layout are edited in the builder — this page only manages the page's settings and metadata.</p>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
            <span wire:loading.remove wire:target="saveEdit">Save page</span>
            <span wire:loading wire:target="saveEdit">Saving…</span>
        </button>
    </form>

    @else
    {{-- ── Metadata tab ── --}}
    <form wire:submit="saveMeta" class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-6 space-y-4">
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Meta title <span class="font-normal text-gray-400">— browser tab & search results</span></label>
            <input wire:model="metaTitle" type="text" maxlength="200" placeholder="{{ $page->name }} — {{ $site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)) }}"
                   class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
            @error('metaTitle')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Meta description</label>
            <textarea wire:model="metaDescription" rows="3" maxlength="5000" placeholder="A short summary shown in search results and link previews…"
                      class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] resize-y"></textarea>
            <p class="text-[10px] text-gray-400 mt-0.5 text-right">{{ mb_strlen($metaDescription) }} chars — aim for 50–160</p>
            @error('metaDescription')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Keywords <span class="font-normal text-gray-400">— comma separated</span></label>
            <input wire:model="metaKeywords" type="text" class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
            @error('metaKeywords')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="olx-adv-lead">More options</div>
        <x-panel-group label="Social sharing (Open Graph)" hint="how links look on WhatsApp, Facebook, LinkedIn…">
            <x-field.text label="og:title (defaults to the meta title)" model="ogTitle" />
            <x-field.textarea label="og:description" model="ogDescription" rows="2" />
            <div>
                <label class="bkf-label">Social image (og:image)</label>
                <x-asset-picker model="ogImage" :site="$site" type="image" placeholder="Pick from assets, or https://…" />
            </div>
        </x-panel-group>
        <x-panel-group label="Search engine controls" hint="canonical URL, robots">
            <x-field.text label="Canonical URL" model="canonicalUrl" placeholder="https://www.example.com{{ $page->url }}" hint="Use when this page's content also lives at another address." />
            <div>
                <label class="bkf-label">Robots</label>
                <select wire:model="robots" class="bkf-input">
                    <option value="">Default (index, follow)</option>
                    <option value="noindex, follow">noindex, follow — hide from search, keep links</option>
                    <option value="index, nofollow">index, nofollow</option>
                    <option value="noindex, nofollow">noindex, nofollow — fully hidden</option>
                </select>
            </div>
        </x-panel-group>
        <x-panel-group label="Custom attributes" hint="free key / value pairs exposed to the site & API">
            @foreach($attrRows as $i => $row)
            <div class="flex items-center gap-2" wire:key="attr-{{ $i }}">
                <input wire:model="attrRows.{{ $i }}.key" placeholder="key" class="w-40 px-2.5 py-1.5 rounded-lg text-xs font-mono bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                <input wire:model="attrRows.{{ $i }}.value" placeholder="value" class="flex-1 px-2.5 py-1.5 rounded-lg text-xs bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                <button type="button" wire:click="removeAttrRow({{ $i }})" class="text-gray-400 hover:text-red-500 text-sm">✕</button>
            </div>
            @error('attrRows.'.$i.'.key')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            @endforeach
            <button type="button" wire:click="addAttrRow" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">＋ Add attribute</button>
        </x-panel-group>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
            <span wire:loading.remove wire:target="saveMeta">Save metadata</span>
            <span wire:loading wire:target="saveMeta">Saving…</span>
        </button>
    </form>
    @endif
    </div>

    {{-- ── RIGHT rail: how the page looks to the world + what's on it ── --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-2" style="color:var(--primary)">Search preview</p>
            <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] p-3">
                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $previewHref ? preg_replace('#^https?://#', '', rtrim($previewHref, '/')) : $site->name.$page->url }}</p>
                <p class="text-[14px] leading-snug font-semibold text-[#1a0dab] dark:text-[#8ab4f8] line-clamp-2 mt-0.5">{{ $metaTitleShown }}</p>
                <p class="text-[12px] leading-snug text-gray-600 dark:text-gray-300 line-clamp-3 mt-1">{{ trim($metaDescription) !== '' ? $metaDescription : 'No meta description yet — search engines will pick text from the page.' }}</p>
            </div>
        </div>

        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">SEO checklist</p>
                <span class="text-[12px] font-bold {{ $seoOk === count($seo) ? 'text-emerald-600' : 'text-amber-600' }}">{{ $seoOk }}/{{ count($seo) }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden mb-3">
                <div class="h-full rounded-full {{ $seoOk === count($seo) ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width:{{ round($seoOk / count($seo) * 100) }}%"></div>
            </div>
            <div class="space-y-2">
                @foreach ($seo as [$label, $ok, $hint])
                    <button type="button" wire:click="setTab('meta')" class="w-full flex items-start gap-2 text-left group">
                        <span class="mt-0.5 w-4 h-4 shrink-0 rounded-full grid place-items-center text-[10px] font-bold {{ $ok ? 'bg-emerald-500 text-white' : 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' }}">{{ $ok ? '✓' : '!' }}</span>
                        <span class="min-w-0">
                            <span class="block text-[12.5px] font-semibold text-gray-800 dark:text-gray-100 group-hover:underline">{{ $label }}</span>
                            @unless ($ok)<span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>@endunless
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Sections on this page</p>
            @forelse ($s['sections'] as $i => $sec)
                <a href="{{ url($site->name.'/connect?page='.$page->id.'&component='.$sec['id']) }}" class="flex items-center gap-2.5 py-1.5 group">
                    <span class="w-6 h-6 rounded-lg grid place-items-center shrink-0 text-[11px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-500">{{ $i + 1 }}</span>
                    <span class="min-w-0 flex-1 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $sec['name'] }}</span>
                    <span class="text-[11px] text-gray-400 shrink-0">{{ $sec['fields'] }} {{ Str::plural('field', $sec['fields']) }}</span>
                </a>
            @empty
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">No sections yet — add some from the Pages list or the editor.</p>
            @endforelse
            @if ($s['collections']->isNotEmpty())
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mt-4 mb-1.5">Collections shown</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($s['collections'] as $col)
                        <a href="{{ route('collections.show', [$site->name, $col->id]) }}" wire:navigate class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 hover:underline">{{ $col->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['All pages', 'Menu & sections', route('pages', ['siteID' => $site->name])],
                    ['Components', 'Reusable blocks', route('site.components', $site->name)],
                    ['Collections', 'Lists on pages', route('collections', $site->name)],
                    ['Analytics', 'Traffic & visits', route('analytics', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 right-6 z-[60] px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium bg-gray-900 text-white">
        <span x-text="toast"></span>
    </div>
</div>
