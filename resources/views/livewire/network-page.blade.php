@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $btnPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-bold shadow-sm';
    $input = 'w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
    $label = 'block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1';
    $money = fn (int $c, ?string $cur = 'gbp') => \App\Support\Money::format($c, $cur ?: 'gbp');
    $cutOf = fn (int $c) => (int) round($c * $cutPct / 100);
    $statusMeta = [
        'pending_consent' => ['Awaiting customer', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'consent_refused' => ['Customer said no', 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'],
        'shared' => ['New lead', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
        'accepted' => ['Accepted', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'],
        'declined' => ['Declined', 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'],
        'converted' => ['Won', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
        'disputed' => ['Disputed', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
        'void' => ['Void', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
        'billed' => ['Billed', 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'],
        'collected' => ['Paid', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
        'expired' => ['Expired', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
        'cancelled' => ['Cancelled', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
    ];
    $pill = fn (string $st) => $statusMeta[$st] ?? [\Illuminate\Support\Str::headline($st), 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'];
    $tabs = ['profile' => 'Profile', 'partners' => 'Find partners', 'sent' => 'Sent', 'received' => 'Received'];
    $memberLabel = $isMember ? ($profile->accepting ? 'Member · accepting referrals' : 'Member · paused') : ($profile ? 'Not joined yet' : 'Not a member');
@endphp
<div>
<x-tri-layout title="Referral network" subtitle="Pass customers to trusted local businesses — and earn when they win the work." :site-name="$site->name"
    :labels="['📊 Overview', '🤝 Network', '⚡ Quick access']">

    {{-- ── LEFT rail: network at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$money($stats['earned'])" label="Earned"
                :sub="$stats['owed'] ? $money($stats['owed']).' on its way' : 'paid out to you'" :href="route('site.earnings', $site->name)"
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lime" :value="number_format($stats['sent'])" label="Referrals sent" :sub="$stats['won'].' won'"
                icon="M17 8l4 4m0 0l-4 4m4-4H3" />
        <x-tile accent="{{ $stats['waiting'] ? 'rose' : 'sky' }}" :value="number_format($stats['received'])" label="Referrals received"
                :sub="$stats['waiting'] ? $stats['waiting'].' waiting for you' : 'all answered'"
                icon="M7 16l-4-4m0 0l4-4m-4 4h18" />
        <x-tile accent="lavender" :value="$stats['rate'].'%'" label="Conversion rate" sub="of referrals you sent"
                icon="M3 13.5L9 7.5l4 4L21 3.5M21 3.5h-5m5 0v5M4 20h16" />
    </div>
    </x-slot:rail>

    {{-- ── CENTRE ── --}}
    <div class="space-y-5" x-data="{ tab: $wire.entangle('tab').live }">
        <x-pill-tabs :tabs="$tabs" :dots="$stats['waiting'] ? ['received'] : []" class="!mb-0" />

        @if ($notice)
            <div class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 text-sm font-semibold">
                <span>{{ $notice }}</span>
                <button type="button" wire:click="$set('notice', '')" class="text-xs opacity-70 hover:opacity-100" aria-label="Dismiss">✕</button>
            </div>
        @endif
        @if ($error)
            <div class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 bg-rose-50 dark:bg-rose-500/10 text-rose-800 dark:text-rose-200 text-sm font-semibold" role="alert">
                <span>{{ $error }}</span>
                <button type="button" wire:click="$set('error', '')" class="text-xs opacity-70 hover:opacity-100" aria-label="Dismiss">✕</button>
            </div>
        @endif

        @unless ($planAllows)
            {{-- Trial / free plan: browse only --}}
            <div class="{{ $panel }} p-5 flex flex-col sm:flex-row sm:items-center gap-4" data-network-upgrade>
                <span class="w-12 h-12 shrink-0 rounded-2xl grid place-items-center bg-amber-50 dark:bg-amber-500/10 text-2xl">🚀</span>
                <div class="flex-1 min-w-0">
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Upgrade to join the referral network</p>
                    <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-0.5">On a paid plan you can send customers to partners, receive new leads and earn a fee for every one that converts. Until then you can browse who's in your area.</p>
                </div>
                <a href="{{ route('account.subscription') }}" class="{{ $btnPrimary }} text-sm px-4 py-2.5 shrink-0" style="background:var(--primary);color:var(--on-primary)">See plans</a>
            </div>
        @endunless

    @if ($tab === 'profile')
        {{-- ══ Profile ══ --}}
        <div class="{{ $panel }} p-5 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $isMember ? 'Your network profile' : 'Join the network' }}</p>
                    <p class="text-xs text-gray-400">What partners see when they look for someone to send a customer to.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $isMember ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300' }}">{{ $memberLabel }}</span>
            </div>

            <fieldset class="space-y-4" @disabled(! $canAct)>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $label }}" for="net-type">Business type</label>
                        <input id="net-type" type="text" wire:model="businessType" list="net-types" class="{{ $input }}" placeholder="e.g. Electrician">
                        <datalist id="net-types">
                            @foreach ($businessTypes as $key => $meta)<option value="{{ $meta['label'] ?? $key }}">@endforeach
                        </datalist>
                        @error('businessType')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="net-area">Area you cover</label>
                        <input id="net-area" type="text" wire:model="area" class="{{ $input }}" placeholder="e.g. Leeds &amp; Bradford">
                        @error('area')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="net-pc">Postcode</label>
                        <input id="net-pc" type="text" wire:model="postcode" class="{{ $input }} uppercase" placeholder="LS1 4AP">
                        @error('postcode')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="net-radius">Radius (km)</label>
                        <input id="net-radius" type="number" min="1" max="500" wire:model="radiusKm" class="{{ $input }}">
                        @error('radiusKm')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <span class="{{ $label }}">Services</span>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @forelse ($services as $i => $s)
                            <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full text-[12px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300" wire:key="svc-{{ $i }}-{{ md5($s) }}">
                                {{ $s }}
                                @if ($canAct)<button type="button" wire:click="removeService({{ $i }})" class="w-4 h-4 grid place-items-center rounded-full hover:bg-indigo-100 dark:hover:bg-indigo-500/20" aria-label="Remove {{ $s }}">✕</button>@endif
                            </span>
                        @empty
                            <span class="text-[12px] text-gray-400">No services added yet.</span>
                        @endforelse
                    </div>
                    <div class="flex gap-2">
                        <input type="text" wire:model="serviceInput" wire:keydown.enter.prevent="addService" class="{{ $input }}" placeholder="Add a service, e.g. Rewiring — press Enter">
                        <button type="button" wire:click="addService" class="{{ $btnSolid }} text-sm px-3 py-2 shrink-0">Add</button>
                    </div>
                </div>

                <div>
                    <label class="{{ $label }}" for="net-pitch">Your pitch <span class="font-normal">(shown to partners)</span></label>
                    <textarea id="net-pitch" wire:model="pitch" rows="3" maxlength="500" class="{{ $input }} resize-y" placeholder="Why should partners send their customers to you?"></textarea>
                    @error('pitch')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-3 items-start">
                    <div>
                        <label class="{{ $label }}" for="net-fee">Fee you pay per converted lead (£)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">£</span>
                            <input id="net-fee" type="number" step="0.01" min="{{ config('network.fee_min_cents') / 100 }}" max="{{ config('network.fee_max_cents') / 100 }}"
                                   wire:model.live.debounce.400ms="feePounds" class="{{ $input }} !pl-7">
                        </div>
                        @error('feePounds')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        <p class="text-[11px] text-gray-400 mt-1">Only charged when a referred customer actually pays you. Added to your Olux bill.</p>
                    </div>
                    <div class="rounded-2xl px-4 py-3 bg-gray-50 dark:bg-white/[0.04] text-[12.5px] space-y-1">
                        <p class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400">You pay per won lead</span><b class="text-gray-900 dark:text-white tabular-nums">{{ $money($feeCents) }}</b></p>
                        <p class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400">Partner who referred earns</span><b class="text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $money($feeCents - $cutOf($feeCents)) }}</b></p>
                        <p class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400">Olux ({{ rtrim(rtrim(number_format($cutPct, 2), '0'), '.') }}%)</span><span class="text-gray-500 tabular-nums">{{ $money($cutOf($feeCents)) }}</span></p>
                        <p class="text-[11px] text-gray-400 pt-1">When you refer someone, you keep the partner's fee minus {{ rtrim(rtrim(number_format($cutPct, 2), '0'), '.') }}%.</p>
                    </div>
                </div>

                <label class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 border border-gray-100 dark:border-white/[0.06]">
                    <span>
                        <span class="block text-[13px] font-bold text-gray-900 dark:text-white">Accepting referrals</span>
                        <span class="block text-[11px] text-gray-400">Turn off to pause new leads without leaving the network.</span>
                    </span>
                    <input type="checkbox" wire:model="accepting" class="w-5 h-5 rounded border-gray-300 dark:border-white/20">
                </label>

                @unless ($isMember)
                    <div class="rounded-2xl p-4 border border-amber-200 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/[0.06] space-y-3">
                        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-amber-700 dark:text-amber-300">Network terms · {{ config('network.terms_version') }}</p>
                        {{-- PLACEHOLDER: the final network agreement text is to be supplied. --}}
                        <p class="text-[12.5px] text-amber-900 dark:text-amber-100" data-terms-placeholder>
                            <b>[PLACEHOLDER — final terms to be supplied.]</b>
                            By joining you agree to only refer customers who have agreed to be passed on, to deal with referred customers fairly,
                            and to pay your stated fee for every referred customer who pays you within {{ config('network.conversion_window_days') }} days.
                            You may dispute a conversion within {{ config('network.dispute_days') }} days. Olux keeps {{ rtrim(rtrim(number_format($cutPct, 2), '0'), '.') }}% of each fee.
                        </p>
                        <label class="flex items-start gap-2.5 text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                            <input type="checkbox" wire:model="termsAgreed" class="mt-0.5 rounded border-gray-300 dark:border-white/20">
                            I agree to the referral network terms
                        </label>
                        @error('termsAgreed')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                @endunless
            </fieldset>

            @if ($canAct)
                <div class="flex flex-wrap items-center gap-2 justify-end pt-1">
                    @if ($isMember)
                        @if ($profile->accepting)
                            <button type="button" wire:click="leave" data-confirm="Leave the referral network? You'll stop receiving new referrals; open ones will run their course."
                                    class="{{ $btnSolid }} text-sm px-4 py-2 !text-rose-600 mr-auto">Leave network</button>
                        @endif
                        <button type="button" wire:click="saveProfile" wire:loading.attr="disabled" class="{{ $btnPrimary }} text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">Save profile</button>
                    @else
                        <button type="button" wire:click="join" wire:loading.attr="disabled" class="{{ $btnPrimary }} text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">Join the network</button>
                    @endif
                </div>
            @elseif (! $canManage)
                <p class="text-[12px] text-gray-400 text-right">You can view the network; ask an owner for “network.manage” to make changes.</p>
            @endif
        </div>

    @elseif ($tab === 'partners')
        {{-- ══ Find partners ══ --}}
        <div class="{{ $panel }} !rounded-2xl p-3 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[12rem]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="q" class="{{ $input }} !pl-9" placeholder="Search services, trades or areas…">
                </div>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.live.debounce.300ms="filterType" list="net-types-f" class="{{ $input }} !w-auto flex-1 min-w-[9rem]" placeholder="Business type">
                <datalist id="net-types-f">@foreach ($businessTypes as $key => $meta)<option value="{{ $meta['label'] ?? $key }}">@endforeach</datalist>
                <input type="text" wire:model.live.debounce.400ms="filterPostcode" class="{{ $input }} !w-28 uppercase" placeholder="Postcode">
                <select wire:model.live="filterRadius" class="{{ $input }} !w-auto">
                    <option value="">Any distance</option>
                    @foreach ([5, 10, 25, 50, 100] as $km)<option value="{{ $km }}">Within {{ $km }} km</option>@endforeach
                </select>
            </div>
        </div>

        @if ($canAct && ! $isMember)
            <p class="text-[12.5px] text-gray-500 dark:text-gray-400 px-1">Join the network from the <button type="button" wire:click="setTab('profile')" class="font-bold underline">Profile</button> tab to refer customers.</p>
        @endif

        @if ($partners->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-sky-50 dark:bg-sky-500/10 text-2xl">🤝</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No partners found</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Try a wider radius or a different search — the network grows as more local businesses join.</p>
            </div>
        @else
            <div class="{{ $viewMode === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 gap-4' : 'space-y-2' }}">
                @foreach ($partners as $p)
                    <div class="{{ $panel }} !rounded-2xl {{ $viewMode === 'compact' ? 'px-4 py-2.5 flex items-center gap-3' : 'p-5 flex flex-col' }}" wire:key="np-{{ $p->id }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white truncate">{{ \App\Livewire\NetworkPage::siteLabel($p->site) }}</p>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" title="Fee they pay per converted lead">{{ $money((int) $p->fee_cents, $p->currency) }} / lead</span>
                            </div>
                            <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $p->business_type }}{{ $p->area ? ' · '.$p->area : '' }}{{ $p->postcode ? ' · '.$p->postcode : '' }}{{ $p->radius_km ? ' · '.$p->radius_km.' km' : '' }}</p>
                            @if ($viewMode !== 'compact')
                                @if ($p->pitch)<p class="mt-2 text-[13px] text-gray-600 dark:text-gray-300 line-clamp-3">{{ $p->pitch }}</p>@endif
                                @if ($p->services)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach (array_slice((array) $p->services, 0, 6) as $s)
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">{{ $s }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <p class="mt-2 text-[11px] text-gray-400">You'd earn {{ $money((int) $p->fee_cents - $cutOf((int) $p->fee_cents), $p->currency) }} if they win the work.</p>
                            @endif
                        </div>
                        @if ($canAct && $isMember)
                            <div class="{{ $viewMode === 'compact' ? 'shrink-0' : 'mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05] flex justify-end' }}">
                                <button type="button" x-on:click="$dispatch('open-refer-partner', { prefill: { to_site_id: @js($p->site_id) } })"
                                        class="{{ $btnPrimary }} text-[12px] px-3.5 py-1.5" style="background:var(--primary);color:var(--on-primary)">Refer a customer</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    @elseif ($tab === 'sent')
        {{-- ══ Sent ══ --}}
        @if ($sentList->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-lime-50 dark:bg-lime-500/10 text-2xl">📤</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No referrals sent yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">When a customer needs something you don't offer, refer them to a partner from Find partners or a contact's page.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($sentList as $r)
                    @php [$stLabel, $stClass] = $pill($r->status); @endphp
                    <div class="{{ $panel }} !rounded-2xl p-4 sm:p-5" wire:key="rs-{{ $r->id }}" x-data="{ open: false }">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $stClass }}">{{ $stLabel }}</span>
                                    <span class="text-[11px] font-mono text-gray-400">{{ $r->reference }}</span>
                                </div>
                                <p class="mt-1.5 text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $r->customer_name }} <span class="font-normal text-gray-400">→</span> {{ \App\Livewire\NetworkPage::siteLabel($r->toSite) }}</p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $r->customer_email }}{{ $r->customer_phone ? ' · '.$r->customer_phone : '' }} · {{ $r->created_at->format('j M Y') }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[11px] text-gray-400">Fee {{ $money($r->fee_cents, $r->currency) }}</p>
                                <p class="text-[14px] font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $money($r->netCents(), $r->currency) }}</p>
                                <p class="text-[10.5px] text-gray-400">you earn</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button" x-on:click="open = ! open" class="{{ $btnSolid }} text-[12px] px-3 py-1.5" x-text="open ? 'Hide timeline' : 'Timeline'">Timeline</button>
                            @if ($canAct && $r->status === 'pending_consent')
                                <button type="button" wire:click="cancel('{{ $r->id }}')" data-confirm="Cancel referral {{ $r->reference }}? The customer's consent link will stop working."
                                        class="{{ $btnSolid }} text-[12px] px-3 py-1.5 !text-rose-600">Cancel referral</button>
                            @endif
                        </div>
                        <ol x-show="open" x-cloak class="mt-3 relative pl-5 space-y-2.5 border-l border-gray-200 dark:border-white/[0.08]">
                            @forelse ($r->events as $e)
                                <li class="relative">
                                    <span class="absolute -left-[1.45rem] top-1 w-2.5 h-2.5 rounded-full bg-indigo-400 ring-4 ring-white dark:ring-[#1d1e2a]"></span>
                                    <p class="text-[12.5px] font-semibold text-gray-800 dark:text-gray-100">{{ \Illuminate\Support\Str::headline($e->type) }}</p>
                                    <p class="text-[10.5px] text-gray-400">{{ $e->created_at?->format('j M Y · g:i A') }}</p>
                                </li>
                            @empty
                                <li class="text-[12px] text-gray-400">Created {{ $r->created_at->format('j M Y · g:i A') }}</li>
                            @endforelse
                        </ol>
                    </div>
                @endforeach
            </div>
        @endif

    @else
        {{-- ══ Received ══ --}}
        @if ($receivedList->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-sky-50 dark:bg-sky-500/10 text-2xl">📥</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No referrals received yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Keep your profile up to date so partners know what to send you.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($receivedList as $r)
                    @php
                        [$stLabel, $stClass] = $pill($r->status);
                        $deadline = \App\Livewire\NetworkPage::disputeDeadline($r);
                        $canDispute = \App\Livewire\NetworkPage::canDispute($r);
                    @endphp
                    <div class="{{ $panel }} !rounded-2xl p-4 sm:p-5 {{ $r->status === 'shared' ? 'ring-2 ring-sky-200 dark:ring-sky-500/30' : '' }}" wire:key="rr-{{ $r->id }}" x-data="{ open: false }">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $stClass }}">{{ $stLabel }}</span>
                                    <span class="text-[11px] font-mono text-gray-400">{{ $r->reference }}</span>
                                </div>
                                <p class="mt-1.5 text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $r->customer_name }}</p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $r->customer_email }}{{ $r->customer_phone ? ' · '.$r->customer_phone : '' }}</p>
                                <p class="text-[11.5px] text-gray-400 mt-0.5">From {{ \App\Livewire\NetworkPage::siteLabel($r->fromSite) }} · {{ ($r->shared_at ?? $r->created_at)->format('j M Y') }}</p>
                                @if ($r->note)<p class="mt-2 text-[12.5px] text-gray-600 dark:text-gray-300 rounded-xl px-3 py-2 bg-gray-50 dark:bg-white/[0.04] whitespace-pre-line">“{{ $r->note }}”</p>@endif
                                @if ($r->decline_reason)<p class="mt-1 text-[11.5px] text-gray-400">Declined: {{ $r->decline_reason }}</p>@endif
                                @if ($r->dispute_reason)<p class="mt-1 text-[11.5px] text-rose-500">Dispute: {{ $r->dispute_reason }}</p>@endif
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[14px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $money($r->fee_cents, $r->currency) }}</p>
                                <p class="text-[10.5px] text-gray-400">fee you'll pay if won</p>
                                @if ($r->status === 'converted' && $deadline)
                                    <p class="mt-1 text-[11px] font-semibold {{ $canDispute ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                                        {{ $canDispute ? 'Dispute by '.$deadline->format('j M Y') : 'Dispute window closed' }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($reasonFor === $r->id)
                            <div class="mt-3 space-y-2">
                                <label class="{{ $label }}" for="reason-{{ $r->id }}">{{ $reasonKind === 'dispute' ? 'Why are you disputing this conversion?' : 'Reason for declining (optional, shared with the partner)' }}</label>
                                <textarea id="reason-{{ $r->id }}" wire:model="reason" rows="2" maxlength="{{ $reasonKind === 'dispute' ? 500 : 300 }}" class="{{ $input }} resize-y"></textarea>
                                @error('reason')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                <div class="flex gap-2 justify-end">
                                    <button type="button" wire:click="cancelReason" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Back</button>
                                    <button type="button" wire:click="submitReason" class="px-3 py-1.5 rounded-lg text-[12px] font-bold bg-rose-600 text-white hover:bg-rose-700">{{ $reasonKind === 'dispute' ? 'Raise dispute' : 'Decline referral' }}</button>
                                </div>
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button" x-on:click="open = ! open" class="{{ $btnSolid }} text-[12px] px-3 py-1.5" x-text="open ? 'Hide timeline' : 'Timeline'">Timeline</button>
                            @if ($canAct && $reasonFor !== $r->id)
                                @if ($r->status === 'shared')
                                    <button type="button" wire:click="accept('{{ $r->id }}')" class="px-3 py-1.5 rounded-lg text-[12px] font-bold bg-emerald-600 text-white hover:bg-emerald-700">Accept</button>
                                    <button type="button" wire:click="startReason('{{ $r->id }}', 'decline')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Decline</button>
                                @endif
                                @if (in_array($r->status, \App\Modules\Network\Models\Referral::OPEN, true))
                                    <button type="button" wire:click="markWon('{{ $r->id }}')" data-confirm="Mark {{ $r->customer_name }} as won? The {{ $money($r->fee_cents, $r->currency) }} referral fee will be added to your Olux bill."
                                            class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Mark won</button>
                                @endif
                                @if ($canDispute)
                                    <button type="button" wire:click="startReason('{{ $r->id }}', 'dispute')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5 !text-rose-600">Dispute</button>
                                @endif
                            @endif
                        </div>
                        <ol x-show="open" x-cloak class="mt-3 relative pl-5 space-y-2.5 border-l border-gray-200 dark:border-white/[0.08]">
                            @forelse ($r->events as $e)
                                <li class="relative">
                                    <span class="absolute -left-[1.45rem] top-1 w-2.5 h-2.5 rounded-full bg-indigo-400 ring-4 ring-white dark:ring-[#1d1e2a]"></span>
                                    <p class="text-[12.5px] font-semibold text-gray-800 dark:text-gray-100">{{ \Illuminate\Support\Str::headline($e->type) }}</p>
                                    <p class="text-[10.5px] text-gray-400">{{ $e->created_at?->format('j M Y · g:i A') }}</p>
                                </li>
                            @empty
                                <li class="text-[12px] text-gray-400">Received {{ ($r->shared_at ?? $r->created_at)->format('j M Y · g:i A') }}</li>
                            @endforelse
                        </ol>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
    </div>

    {{-- ══ RIGHT rail ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Membership</p>
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $memberLabel }}</p>
            @if ($profile)
                <dl class="mt-3 space-y-1.5 text-[12.5px]">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Type</dt><dd class="font-semibold text-gray-900 dark:text-white truncate">{{ $profile->business_type ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Area</dt><dd class="font-semibold text-gray-900 dark:text-white truncate">{{ $profile->area ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Your fee</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $money((int) $profile->fee_cents, $profile->currency) }} / lead</dd></div>
                    @if ($profile->terms_accepted_at)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Joined</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $profile->terms_accepted_at->format('j M Y') }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Set up your profile to start receiving local leads.</p>
            @endif
            @unless ($planAllows)
                <a href="{{ route('account.subscription') }}" class="mt-3 block text-[12.5px] font-bold underline" style="color:var(--primary)">Upgrade to take part →</a>
            @endunless
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">How it works</p>
            <ol class="space-y-3">
                @foreach ([
                    ['Refer', 'A customer needs something you don\'t do — send them to a partner.'],
                    ['They agree', 'We ask the customer first. Nothing is shared without their consent.'],
                    ['You earn', 'If the partner wins the work you get their fee, minus '.rtrim(rtrim(number_format($cutPct, 2), '0'), '.').'%.'],
                ] as $i => [$h, $t])
                    <li class="flex gap-3">
                        <span class="w-6 h-6 shrink-0 rounded-full grid place-items-center text-[11px] font-bold" style="background:var(--primary);color:var(--on-primary)">{{ $i + 1 }}</span>
                        <span><span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $h }}</span><span class="block text-[12px] text-gray-500 dark:text-gray-400">{{ $t }}</span></span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Earnings', 'Payouts & method', route('site.earnings', $site->name)],
                    ['Contacts', 'Refer a customer', route('site.contacts', $site->name)],
                ] as [$lbl, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $lbl }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>

<livewire:refer-to-partner :site="$site" />
</div>
