<x-page-layout title="Marketplace" subtitle="Add or remove features for this site.">
    @php
        $mpAll = \App\Features\FeatureRegistry::all();
        $mpEnabled = collect($mpAll)->filter(fn ($f) => $site->hasFeature($f['key']))->count();
    @endphp
    <x-slot:stats>
        <x-stat-tile label="Available apps" :value="count($mpAll)" color="#6366f1"
            icon="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
        <x-stat-tile label="Enabled" :value="$mpEnabled" :sub="count($mpAll).' total'" color="#10b981"
            :bar="count($mpAll) ? round($mpEnabled / count($mpAll) * 100) : 0"
            icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-stat-tile label="Payments" :value="$site->stripeReady() ? 'Connected' : 'Off'"
            :color="$site->stripeReady() ? '#10b981' : '#f59e0b'"
            icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </x-slot:stats>

<div
     x-data="{ mtab:'features', toast:'', toastType:'success' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
     ">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Marketplace</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Add or remove features for <span class="font-medium text-gray-600 dark:text-gray-300">{{ ucwords(str_replace('-', ' ', $site->name)) }}</span>.</p>
        </div>
        @if($this->needsPayments)
        <button wire:click="openPayments"
                class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border transition-colors
                       {{ $site->stripeReady()
                            ? 'border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'
                            : 'border-amber-300 dark:border-amber-500/40 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            {{ $site->stripeReady() ? 'Payments connected' : 'Connect Stripe' }}
        </button>
        @endif
    </div>

    @unless($canManage)
    <div class="mb-5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        You have view-only access. Only the site owner or admins can enable features or change settings.
    </div>
    @endunless

    {{-- Payments-needed banner --}}
    @if($this->needsPayments && ! $site->stripeReady())
    <div class="mb-5 px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 text-sm text-indigo-700 dark:text-indigo-300 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        A payment-enabled feature is on, but Stripe isn't connected yet. Click <strong>Connect Stripe</strong> to start accepting payments.
    </div>
    @endif

    {{-- Tabs: Features · Templates --}}
    <div class="flex items-center gap-1 mb-5 p-1 rounded-xl bg-gray-100 dark:bg-white/[0.05] w-max">
        <button @click="mtab='features'" :class="mtab==='features' ? 'bg-white dark:bg-white/[0.12] text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors">Features</button>
        <button @click="mtab='templates'" :class="mtab==='templates' ? 'bg-white dark:bg-white/[0.12] text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors">Templates</button>
    </div>

    {{-- ════════ FEATURES ════════ --}}
    <div x-show="mtab==='features'">
    {{-- Feature cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($this->features as $f)
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center {{ $f['enabled'] ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}">
                    <x-dynamic-component :component="'icons.'.$f['icon']" class="w-5 h-5" />
                </div>
                <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full {{ $f['enabled'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                    {{ $f['enabled'] ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $f['name'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 leading-relaxed flex-1">{{ $f['description'] }}</p>

            @if(($f['tier'] ?? 'basic') === 'premium')
                <span class="inline-flex items-center text-[9px] font-extrabold tracking-wider px-1.5 py-0.5 rounded mb-1"
                      style="background:color-mix(in srgb, var(--primary) 18%, transparent); color:var(--primary)">PREMIUM</span>
            @else
                <span class="inline-flex items-center text-[9px] font-extrabold tracking-wider px-1.5 py-0.5 rounded mb-1 bg-gray-100 dark:bg-white/[0.06] text-gray-500">BASIC</span>
            @endif
            @if(($f['needs_payments'] ?? false))
            <p class="mt-2 inline-flex items-center gap-1 text-[10px] font-medium text-gray-400 dark:text-gray-500">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Requires Stripe
            </p>
            @endif

            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-50 dark:border-white/[0.04]">
                {{-- Toggle --}}
                <button wire:click="toggle('{{ $f['key'] }}')" @disabled(! $canManage)
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:opacity-40
                               {{ $f['enabled'] ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-white/[0.1]' }}">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $f['enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $f['enabled'] ? 'On' : 'Off' }}</span>

                @if(!empty($f['settings']))
                <button wire:click="openSettings('{{ $f['key'] }}')"
                        class="ml-auto text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Settings
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    </div>{{-- /features tab --}}

    {{-- ════════ TEMPLATES ════════ --}}
    <div x-show="mtab==='templates'" x-cloak class="space-y-8">

        {{-- Template-app submissions (moderators): staging folder → review → publish --}}
        @if(auth()->user() && in_array(auth()->user()->email, (array) config('templates.moderators'), true))
            <div class="rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[#1e1f2b] p-5">
                <livewire:template-submissions />
            </div>
        @endif

        {{-- Upload a template package --}}
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-white/[0.12] bg-gray-50/60 dark:bg-white/[0.02] p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Upload a template</h3>
                    <p class="text-xs text-gray-400 mt-0.5">A <code class="text-[11px]">.zip</code> containing <code class="text-[11px]">template.json</code>, an optional <code class="text-[11px]">thumbnail.png</code> and an <code class="text-[11px]">assets/</code> folder. Installs to this site only —
                        <a href="{{ route('my.templates') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">publish to the marketplace →</a></p>
                </div>
                <form wire:submit="uploadTemplate" class="flex items-center gap-2">
                    <input type="file" wire:model="templateZip" accept=".zip"
                           class="text-xs text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 file:cursor-pointer" @disabled(!$canManage)>
                    <button type="submit" wire:loading.attr="disabled" wire:target="uploadTemplate,templateZip"
                            class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-xs font-semibold" @disabled(!$canManage)>
                        <span wire:loading.remove wire:target="uploadTemplate">Upload</span>
                        <span wire:loading wire:target="uploadTemplate">Importing…</span>
                    </button>
                </form>
            </div>
            <p wire:loading wire:target="templateZip" class="text-[11px] text-gray-400 mt-2">Uploading file…</p>
            @error('templateZip') <p class="text-[11px] text-rose-500 mt-2">{{ $message }}</p> @enderror
            @if($templateError) <p class="text-[11px] text-rose-500 mt-2">{{ $templateError }}</p> @endif
        </div>

        {{-- Installed on this site --}}
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Installed on this site</h3>
            @if($this->installedTemplates->isEmpty())
                <p class="text-sm text-gray-400">No templates installed yet — install one below or upload a <code>.zip</code>.</p>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($this->installedTemplates as $it)
                <div wire:key="inst-{{ $it->id }}" class="rounded-2xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] overflow-hidden flex flex-col">
                    <div class="h-28 bg-gradient-to-br {{ $it->gradient_class ?: 'from-slate-400 to-slate-600' }} relative overflow-hidden">
                        @if($it->thumbnailUrl())
                            <img src="{{ $it->thumbnailUrl() }}" alt="{{ $it->name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-top">
                        @endif
                        <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider" style="background:rgba(0,0,0,.45);color:#fff">{{ $it->isBuiltin() ? 'Built-in' : 'Custom' }}</span>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ $it->name }}</h4>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2 flex-1">{{ $it->description }}</p>
                        @if($it->template_id)
                            @php $myStars = $this->myRatings[$it->template_id] ?? 0; @endphp
                            <div class="flex items-center gap-0.5 mt-2" title="Rate this template">
                                @for($s = 1; $s <= 5; $s++)
                                    <button type="button" wire:click="rateTemplate({{ $it->template_id }}, {{ $s }})"
                                            class="text-sm leading-none {{ $s <= $myStars ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600 hover:text-amber-300' }}">★</button>
                                @endfor
                                <span class="text-[10px] text-gray-400 ml-1">{{ $myStars ? 'Your rating' : 'Rate' }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                            <span class="text-[10px] text-gray-400">{{ $it->pageCount() }} {{ Str::plural('page', $it->pageCount()) }}</span>
                            <button wire:click="uninstallTemplate({{ $it->id }})" data-confirm="Remove this template from the site?" @disabled(!$canManage)
                                    class="text-xs font-semibold text-rose-500 hover:underline disabled:opacity-40">Remove</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Curated, first-party template APPS — preview == published site, exactly --}}
        @if(count($this->curated))
        <div class="mb-8">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Featured templates</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($this->curated as $c)
                <div wire:key="cur-{{ $c['key'] }}" class="rounded-2xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] overflow-hidden flex flex-col">
                    <div class="aspect-[16/10] bg-center bg-cover"
                         style="background-color: {{ $c['accent'] }};{{ $c['thumbnail'] ? 'background-image:url(\''.$c['thumbnail'].'\');' : '' }}"></div>
                    <div class="p-4 flex flex-col flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $c['name'] }}</h4>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background: {{ $c['accent'] }}22; color: {{ $c['accent'] }};">{{ $c['category'] }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2 flex-1">{{ $c['description'] ?: 'A first-party template — the preview is exactly what publishes.' }}</p>
                        <div class="flex items-center gap-2 mt-4">
                            <a href="{{ $c['previewUrl'] }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C4 7 8 4 12 4s8 3 9.5 8c-1.5 5-5.5 8-9.5 8s-8-3-9.5-8z"/></svg>
                                Live preview
                            </a>
                            <button type="button" wire:click="useCurated('{{ $c['key'] }}')" wire:loading.attr="disabled"
                                class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                                {{ $c['installed'] ? 'Use again' : 'Use this template' }}
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Available to install — DB catalog (search · filter · paginate) --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Browse templates</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input wire:model.live.debounce.350ms="tplSearch" type="text" placeholder="Search templates…"
                               class="w-48 pl-8 pr-3 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    </div>
                    <select wire:model.live="tplCategory" class="text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 px-2 py-1.5">
                        <option value="">All categories</option>
                        @foreach($this->templateCategories as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                    <select wire:model.live="tplPrice" class="text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 px-2 py-1.5">
                        <option value="">Any price</option><option value="free">Free</option><option value="paid">Paid</option>
                    </select>
                    <select wire:model.live="tplSort" class="text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 px-2 py-1.5">
                        <option value="popular">Popular</option><option value="new">Newest</option>
                        <option value="rating">Top rated</option>
                        <option value="price-low">Price ↑</option><option value="price-high">Price ↓</option>
                    </select>
                </div>
            </div>

            @php $installedIds = $this->installedTemplateIds; @endphp
            @if($this->templates->isEmpty())
                <p class="text-sm text-gray-400 py-8 text-center">No templates match your filters.</p>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($this->templates as $tpl)
                <div wire:key="cat-{{ $tpl->id }}" class="rounded-2xl border border-gray-200 dark:border-white/[0.07] bg-white dark:bg-[#1d1e2a] overflow-hidden flex flex-col">
                    <div class="h-28 bg-gradient-to-br {{ $tpl->gradient_class ?: 'from-slate-400 to-slate-600' }} relative overflow-hidden">
                        @if($tpl->thumbnail_url)
                            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-top">
                        @endif
                        <span class="absolute top-2 right-2 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider" style="background:rgba(0,0,0,.45);color:#fff">{{ $tpl->category }}</span>
                        <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded-full {{ $tpl->isFree() ? 'bg-emerald-500 text-white' : 'bg-gray-900/80 text-white' }}">{{ $tpl->priceLabel() }}</span>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ $tpl->name }}</h4>
                        @if($tpl->rating_count)
                            @php $rs = (int) round($tpl->rating_avg); @endphp
                            <div class="flex items-center gap-1 mt-0.5">
                                <span class="text-amber-400 text-xs leading-none">{{ str_repeat('★', $rs).str_repeat('☆', 5 - $rs) }}</span>
                                <span class="text-[10px] text-gray-400">{{ number_format($tpl->rating_avg, 1) }} ({{ $tpl->rating_count }})</span>
                            </div>
                        @endif
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2 flex-1">{{ $tpl->description }}</p>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                            <a href="{{ url('nuxt-preview/') }}?template={{ urlencode($tpl->slug) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C4 7 8 4 12 4s8 3 9.5 8c-1.5 5-5.5 8-9.5 8s-8-3-9.5-8z"/></svg>
                                Live preview
                            </a>
                            @if(in_array($tpl->id, $installedIds, true))
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Installed
                                </span>
                            @else
                                <button wire:click="installFromCatalog({{ $tpl->id }})" @disabled(!$canManage)
                                        class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-xs font-semibold">
                                    {{ $tpl->isFree() ? 'Install' : 'Get '.$tpl->priceLabel() }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="mt-5">{{ $this->templates->links() }}</div>
            @endif
        </div>
    </div>{{-- /templates tab --}}

    {{-- ════════ Feature settings drawer ════════ --}}
    @if($settingsKey)
    @php $def = \App\Features\FeatureRegistry::get($settingsKey); @endphp
    <div class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/40" wire:click="closeSettings"></div>
        <div class="relative w-full max-w-md h-full bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.05] shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $def['name'] }} settings</h2>
                <button wire:click="closeSettings" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="saveSettings" class="p-6 space-y-5">
                @foreach($def['settings'] as $name => $field)
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">{{ $field['label'] ?? $name }}</label>
                    @switch($field['type'] ?? 'text')
                        @case('select')
                            <select wire:model="form.{{ $name }}" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                @foreach($field['options'] as $opt)
                                <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                                @endforeach
                            </select>
                            @break
                        @case('number')
                            <input wire:model="form.{{ $name }}" type="number" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            @break
                        @case('textarea')
                            <textarea wire:model="form.{{ $name }}" rows="3" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                            @break
                        @default
                            <input wire:model="form.{{ $name }}" type="text" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    @endswitch
                </div>
                @endforeach

                <div class="flex gap-2 pt-2">
                    <button type="submit" @disabled(! $canManage) class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">Save settings</button>
                    <button type="button" wire:click="closeSettings" class="px-4 py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ════════ Stripe payments drawer ════════ --}}
    @if($showPayments)
    <div class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/40" wire:click="$set('showPayments', false)"></div>
        <div class="relative w-full max-w-md h-full bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.05] shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Connect Stripe</h2>
                <button wire:click="$set('showPayments', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="savePayments" class="p-6 space-y-5">
                <p class="text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
                    Paste your own Stripe API keys so payments go directly to your account. Find them in the
                    <span class="font-medium text-gray-600 dark:text-gray-300">Stripe Dashboard → Developers → API keys</span>. Secrets are stored encrypted.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Publishable key</label>
                    <input wire:model="pubKey" type="text" placeholder="pk_test_…" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                    @error('pubKey') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                        Secret key @if($hasSecret)<span class="text-emerald-500 normal-case font-normal">· saved (leave blank to keep)</span>@endif
                    </label>
                    <input wire:model="secretKey" type="password" placeholder="{{ $hasSecret ? '•••••••••••••••• (saved)' : 'sk_test_…' }}" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Webhook signing secret</label>
                    <input wire:model="webhookSecret" type="password" placeholder="whsec_…" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5">In Stripe, add an endpoint pointing to <code class="text-indigo-500">{{ url($site->name.'/store/webhook') }}</code> (and <code class="text-indigo-500">/donate/webhook</code>) for event <code>checkout.session.completed</code>, then paste its signing secret here.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Currency</label>
                    <select wire:model="siteCurrency" class="w-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#22232f] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach(\App\Support\Money::options() as $code => $label)
                            <option value="{{ $code }}">{{ strtoupper($code) }} — {{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5">Used for every price, invoice, booking and checkout on this site. Default: £ British Pound.</p>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" @disabled(! $canManage) class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">Save Stripe keys</button>
                    <button type="button" wire:click="$set('showPayments', false)" class="px-4 py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium"
         :class="toastType === 'success' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white'">
        <svg x-show="toastType==='success'" class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <svg x-show="toastType==='error'" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        <span x-text="toast"></span>
    </div>
</x-page-layout>
