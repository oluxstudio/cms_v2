{{-- Full-screen canvas, dashboard idiom: left rail | centered main content --}}
<div class="min-h-full flex flex-col app-bg"
     x-data="{ toast:'' }"
     x-init="$watch('$wire.successMessage', v => { if(v){ toast=v; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } })">

    <x-bg-ambient />

    {{-- ── Header row ── --}}
    <div class="flex flex-wrap items-center gap-3 px-6 py-5 shrink-0">
        <div class="min-w-0 flex-1">
            <a href="{{ route('pages', ['siteID' => $site->name]) }}" class="text-xs font-semibold text-gray-400 hover:text-indigo-500">← Pages</a>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white truncate leading-tight">{{ $page->name }}</h1>
            <p class="text-xs text-gray-400 font-mono">{{ $page->url }}</p>
        </div>
        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $page->is_published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
            {{ $page->is_published ? 'Live' : 'Draft' }}
        </span>
        @if($preview = $site->previewUrl($page->url))
            <a href="{{ $preview }}" target="_blank"
               class="px-3.5 py-2 rounded-xl border border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 bg-white/60 dark:bg-white/[0.04]">↗ View page</a>
        @endif
    </div>

    {{-- ── Panes: sticky left rail | centered main content ── --}}
    <div class="flex-1 flex flex-col lg:flex-row min-h-0">

        {{-- ════ LEFT RAIL ════ --}}
        @php $s = $this->summary; @endphp
        <aside class="w-full max-w-[25rem] mx-auto lg:mx-0 lg:w-[280px] shrink-0 px-5 pb-6 space-y-4
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            {{-- Page identity card --}}
            <div class="rounded-3xl p-5 shadow-sm text-white" style="background:linear-gradient(150deg,#1f2330,#11131c)">
                <p class="text-sm font-bold truncate">{{ $page->name }}</p>
                <p class="text-xs text-white/50 font-mono truncate mt-0.5">{{ $page->url }}</p>
                <div class="grid grid-cols-2 gap-2 text-center mt-4">
                    <div class="rounded-xl bg-white/[0.07] px-2 py-2.5">
                        <p class="text-lg font-extrabold leading-none">{{ $s['components'] }}</p>
                        <p class="text-[10px] text-white/50 mt-1">sections</p>
                    </div>
                    <div class="rounded-xl bg-white/[0.07] px-2 py-2.5">
                        <p class="text-lg font-extrabold leading-none">{{ $s['fields'] }}</p>
                        <p class="text-[10px] text-white/50 mt-1">fields</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
                <x-tile accent="lime" :value="number_format($s['visits_30d'])" label="visits · 30 days"
                        sub="this page only" />
                <x-tile accent="cocoa" :value="$s['updated']?->diffForHumans(short: true) ?? '—'" label="last updated"
                        :sub="$page->is_published ? 'live on the site' : 'draft'" />
            </div>
        </aside>

        {{-- ════ MAIN CONTENT ════ --}}
        <div class="flex-1 min-w-0 px-5 pb-6">
            <div class="{{ $tab === 'content' ? '' : 'max-w-3xl mx-auto' }}">
    {{-- Tabs --}}
    <div class="flex gap-1.5 mb-5">
        @foreach(['edit' => '✏️ Edit', 'meta' => '🏷 Page attributes & meta tags', 'content' => '📝 Content'] as $key => $label)
            <button wire:click="setTab('{{ $key }}')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                           {{ $tab === $key ? 'bg-indigo-600 text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.08]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'content')
    {{-- ── Content tab: the /connect inline editor, scoped to this page ── --}}
    <div class="min-w-0" wire:ignore>
        <livewire:connect-review-page :site="$site" :preview-path="$page->url" :embedded="true" wire:key="content-editor-{{ $page->id }}" />
    </div>

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
            <x-field.text label="og:image URL" model="ogImage" placeholder="https://…" />
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
            </div>{{-- /centered column --}}
        </div>{{-- /main section --}}
    </div>{{-- /panes --}}

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 right-6 z-[60] px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium bg-gray-900 text-white">
        <span x-text="toast"></span>
    </div>
</div>
