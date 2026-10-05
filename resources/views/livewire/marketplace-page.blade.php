@php
    $mpAll = \App\Features\FeatureRegistry::all();
    $mpEnabled = collect($mpAll)->filter(fn ($f) => $site->hasFeature($f['key']))->count();
    $mpPay = collect($this->features)->filter(fn ($f) => ($f['needs_payments'] ?? false) && $f['enabled'])->count();
@endphp
<x-tri-layout title="Add-ons" :subtitle="'Add or remove features for '.ucwords(str_replace('-', ' ', $site->name)).'.'" :site-name="$site->name"
    :labels="['📊 Overview', '🧩 Add-ons', 'ℹ️ Summary']">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-gray-100 dark:bg-white/[0.05]">
            <a href="{{ url($site->name.'/design') }}" class="px-4 py-1.5 rounded-full text-sm font-semibold text-gray-500 dark:text-gray-400">Design</a>
            <span class="px-4 py-1.5 rounded-full text-sm font-semibold bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white shadow-sm">Add-ons</span>
            <a href="{{ url($site->name.'/publish') }}" class="px-4 py-1.5 rounded-full text-sm font-semibold text-gray-500 dark:text-gray-400">Domain</a>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: page stats ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$mpEnabled.' of '.count($mpAll)" label="Features enabled"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                :sub="$mpEnabled ? 'powering this site' : 'switch some on below'" />
        <x-tile accent="lime" :value="count($mpAll)" label="Available apps"
                icon="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"
                sub="in the catalogue" />
        <x-tile accent="lavender" :value="$site->paymentsEnabled() ? 'On' : ($site->paymentSettings?->isConfigured() ? 'Off' : 'Not set up')" label="Payments"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                :sub="$mpPay ? $mpPay.' add-on'.($mpPay === 1 ? '' : 's').' take money' : 'no paid add-ons on'" />
        <x-tile accent="sky" :value="collect($mpAll)->where('tier', 'premium')->count()" label="Premium apps"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"
                sub="on higher plans" />
    </div>
    </x-slot:rail>

{{-- ══ CENTER: the add-ons themselves ══ --}}
<div class="max-w-[52rem] mx-auto"
    x-data="{ view: localStorage.getItem('mp-view') || 'grid', toast:'', toastType:'success' }"
    x-init="
        $watch('view', v => localStorage.setItem('mp-view', v));
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
    ">

    <style>
        /* List view: media-led cards become horizontal rows; feature cards
           stay stacked, just full-width. */
        .mp-list > div:has(> .h-28), .mp-list > div:has(> .h-36),
        .mp-list > div:has(> [class*="aspect-"]) { flex-direction: row !important; align-items: stretch; }
        .mp-list > div > div:first-child.h-28,
        .mp-list > div > div:first-child.h-36,
        .mp-list > div > div:first-child[class*="aspect-"] {
            width: 200px; min-height: 110px; height: auto !important;
            aspect-ratio: auto !important; flex-shrink: 0;
        }
        .mp-list > div > div:last-child { flex: 1; min-width: 0; }
        @media (max-width: 640px) {
            .mp-list > div > div:first-child.h-28,
            .mp-list > div > div:first-child.h-36,
            .mp-list > div > div:first-child[class*="aspect-"] { width: 120px; }
        }
    </style>

    @unless($canManage)
    <div class="mb-5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        You have view-only access. Only the site owner or admins can enable features or change settings.
    </div>
    @endunless

    {{-- Payments-needed banner --}}
    @if($this->needsPayments && ! $site->stripeReady())
    <div class="mb-5 px-4 py-3 rounded-xl bg-white dark:bg-[#1d1e2a] border text-sm flex items-center gap-2"
         style="border-color:color-mix(in srgb, var(--primary) 35%, transparent); color:var(--primary)">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-gray-700 dark:text-gray-200">A payment-enabled feature is on, but this site isn't accepting payments yet — <a href="{{ url($site->name.'/payments') }}" class="font-bold underline" style="color:var(--primary)">open the Payments page</a> to start taking money.</span>
    </div>
    @endif

    @if (session('mp-message'))
        <p class="mb-4 px-4 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ session('mp-message') }}</p>
    @endif

    {{-- toolbar: grid/list toggle --}}
    <div class="flex items-center justify-between mb-4">
        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ count($mpAll) }} apps · {{ $mpEnabled }} enabled</p>
        <div class="flex items-center gap-1 p-1 rounded-xl bg-gray-100 dark:bg-white/[0.05]">
            <button @click="view='grid'" title="Grid view" aria-label="Grid view"
                    :class="view==='grid' ? 'bg-white dark:bg-[#1d1e2a] shadow-sm' : ''"
                    class="px-2.5 py-1.5 rounded-lg transition-colors text-gray-600 dark:text-gray-300">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
            </button>
            <button @click="view='list'" title="List view" aria-label="List view"
                    :class="view==='list' ? 'bg-white dark:bg-[#1d1e2a] shadow-sm' : ''"
                    class="px-2.5 py-1.5 rounded-lg transition-colors text-gray-600 dark:text-gray-300">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Feature cards --}}
    <div :class="view==='list' ? 'mp-list flex flex-col gap-3' : 'grid grid-cols-1 sm:grid-cols-2 gap-4'">
        @foreach($this->features as $f)
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center {{ $f['enabled'] ? 'text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}"
                     @if($f['enabled']) style="background:var(--primary);color:var(--on-primary)" @endif>
                    <x-dynamic-component :component="'icons.'.$f['icon']" class="w-5 h-5" />
                </div>
                <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full {{ $f['enabled'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                    {{ $f['enabled'] ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $f['name'] }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 leading-relaxed flex-1">{{ $f['description'] }}</p>
            @if (! $f['enabled'] && ! empty($f['nav']))
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1.5">Adds to your menu: <span class="font-semibold text-gray-500 dark:text-gray-400">{{ collect($f['nav'])->pluck('label')->implode(', ') }}</span></p>
            @endif

            @if(($f['tier'] ?? 'basic') === 'premium')
                <span class="inline-flex items-center text-[9px] font-extrabold tracking-wider px-1.5 py-0.5 rounded mb-1 mt-2 w-max"
                      style="background:color-mix(in srgb, var(--primary) 18%, transparent); color:var(--primary)">PREMIUM</span>
            @else
                <span class="inline-flex items-center text-[9px] font-extrabold tracking-wider px-1.5 py-0.5 rounded mb-1 mt-2 w-max bg-gray-100 dark:bg-white/[0.06] text-gray-500">BASIC</span>
            @endif
            @if(($f['needs_payments'] ?? false))
            <p class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium text-gray-400 dark:text-gray-500">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Requires Stripe
            </p>
            @endif

            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-50 dark:border-white/[0.04]">
                {{-- Toggle --}}
                <label class="relative inline-flex items-center {{ $canManage ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                    <input type="checkbox" class="sr-only" @checked($f['enabled']) @disabled(! $canManage)
                           wire:click="toggle('{{ $f['key'] }}')">
                    <span class="bkf-switch"></span>
                </label>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $f['enabled'] ? 'On' : 'Off' }}</span>

                <span class="ml-auto flex items-center gap-2.5">
                    @if ($f['enabled'])
                        @foreach ($f['nav'] ?? [] as $navItem)
                            <a href="{{ url($site->name.'/'.$navItem['seg']) }}" wire:navigate
                               class="text-xs font-semibold hover:underline" style="color:var(--primary)">{{ $navItem['label'] }} →</a>
                        @endforeach
                    @endif
                    @if(!empty($f['settings']))
                    <button wire:click="openSettings('{{ $f['key'] }}')"
                            class="text-xs font-semibold text-gray-500 dark:text-gray-400 hover:underline">
                        Settings
                    </button>
                    @endif
                </span>
            </div>
        </div>
        @endforeach
    </div>

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
                            <select wire:model="form.{{ $name }}" class="bkf-input w-full rounded-xl px-4 py-2.5 text-sm">
                                @foreach($field['options'] as $opt)
                                <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                                @endforeach
                            </select>
                            @break
                        @case('number')
                            <input wire:model="form.{{ $name }}" type="number" class="bkf-input w-full rounded-xl px-4 py-2.5 text-sm">
                            @break
                        @case('textarea')
                            <textarea wire:model="form.{{ $name }}" rows="3" class="bkf-input w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
                            @break
                        @default
                            <input wire:model="form.{{ $name }}" type="text" class="bkf-input w-full rounded-xl px-4 py-2.5 text-sm">
                    @endswitch
                </div>
                @endforeach

                <div class="flex gap-2 pt-2">
                    <button type="submit" @disabled(! $canManage) class="fx flex-1 py-2.5 disabled:opacity-40 text-sm font-semibold rounded-xl" style="background:var(--primary);color:var(--on-primary)">Save settings</button>
                    <button type="button" wire:click="closeSettings" class="px-4 py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl bg-white dark:bg-[#1d1e2a] hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
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
</div>

    {{-- ══ RIGHT rail: page summary + related ══ --}}
    <x-slot:quick>
        {{-- What's on for this site --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">On for this site</h3>
            @php $onList = collect($this->features)->filter(fn ($f) => $f['enabled'])->values(); @endphp
            @forelse ($onList as $f)
                <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="w-4 h-4 rounded-full grid place-items-center bg-emerald-500 shrink-0">
                        <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-[12.5px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $f['name'] }}</span>
                    @if(($f['needs_payments'] ?? false))<span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.08] text-gray-500">£</span>@endif
                </div>
            @empty
                <p class="text-[11px] text-gray-400 py-2">Nothing enabled yet — switch on your first add-on from the list.</p>
            @endforelse
            @if($this->needsPayments)
                <a href="{{ url($site->name.'/payments') }}" wire:navigate class="fx block mt-2 text-[12px] font-bold hover:underline" style="color:var(--primary)">
                    {{ $site->paymentsEnabled() ? 'Payments on — manage →' : ($site->paymentSettings?->isConfigured() ? 'Payments off — turn on →' : 'Connect Stripe →') }}
                </a>
            @endif
        </div>

        {{-- Current template --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Current template</h3>
            @if($cur = $this->currentTemplate)
                <div class="rounded-xl overflow-hidden bg-gradient-to-br {{ $cur['gradient'] }} aspect-[16/9] relative mb-3">
                    @if($cur['thumbnail'])
                        <img src="{{ $cur['thumbnail'] }}" alt="{{ $cur['name'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-top">
                    @endif
                    <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider text-white" style="background:var(--primary)">In use</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ $cur['name'] }}</h4>
                    @if($cur['category'])<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.08] text-gray-500 dark:text-gray-300">{{ $cur['category'] }}</span>@endif
                </div>
                <p class="text-[11px] text-gray-400 mt-1">
                    @if($cur['appliedAt'])Applied {{ $cur['appliedAt'] }}@endif
                    @if($cur['pages']) · {{ $cur['pages'] }} {{ Str::plural('page', $cur['pages']) }}@endif
                </p>
                <div class="flex items-center gap-2 mt-3">
                    <a href="{{ url($site->name.'/connect') }}" wire:navigate
                       class="fx px-3 py-1.5 rounded-xl text-white text-xs font-semibold" style="background:var(--primary);color:var(--on-primary)">Edit site →</a>
                    <x-preview-button :href="$site->visitorPreviewUrl()" label="Preview" small />
                </div>
            @else
                <p class="text-xs text-gray-400 rounded-xl border border-dashed border-gray-200 dark:border-white/[0.08] px-3 py-5 text-center">No template applied yet.</p>
            @endif
        </div>

        {{-- Related --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Design', 'the template this site runs on', $site->name.'/design'],
                ['Payments', 'Stripe & taking money', $site->name.'/payments'],
                ['Go live', 'domain & serving status', $site->name.'/publish'],
            ] as [$rl, $rd, $ru])
                <a href="{{ url($ru) }}" class="fx flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
