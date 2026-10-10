@php
    $avatarColors = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#10b981','#f97316','#ef4444','#14b8a6'];
    // Closure, not a named function — a view rendered twice in one process
    // (Livewire updates, tests) would fatally redeclare a plain function.
    $siteAvatarColor = fn (string $name) => $avatarColors[ord($name[0]) % count($avatarColors)];

    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $room = $this->planRoom;
    $all = collect($sites);
    $liveCount = $all->where('live', true)->count();
    $pct = $progress['total'] ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
    $nextStep = collect($steps)->firstWhere('done', false);
    $pillOutline = 'inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-300';

    // "How to use Olux" — the journey from a blank account to a live site.
    $guide = [
        ['🌐', 'Create a site', 'Click New site, give it your business name and pick what kind of business it is. You get the right starter pages and a free web address straight away.'],
        ['🎨', 'Choose a template', 'Open the site, then Templates. Pick a design — you can switch later and your pages and content stay.'],
        ['✏️', 'Edit your content', 'Open Edit site and click any words or pictures on the page to change them. Pages, Posts and Collections hold the rest.'],
        ['📥', 'Capture customers', 'Every form, booking or order lands in Contacts, and you get an alert. Reply, add notes and track each lead to won.'],
        ['🧩', 'Add what you need', 'Switch on Add-ons such as bookings, a shop, invoices or donations. Connect Stripe once under Payments to take money.'],
        ['🚀', 'Go live', 'Open Go live to buy a domain or connect one you own. Your site keeps its free address until then.'],
    ];
    // What's inside a site — the menu you'll see after opening one.
    $inside = [
        ['dashboard', 'Dashboard', 'today at a glance: new contacts, bookings, tasks'],
        ['pencil', 'Edit site', 'your live site with editing switched on'],
        ['page-fill', 'Content', 'pages, posts, collections, assets'],
        ['contacts', 'Audience', 'messages, forms, contacts, tasks, referrals'],
        ['shop', 'Commerce', 'store, bookings, invoices — once switched on'],
        ['graph-up', 'Site', 'properties, email, design, analytics, go live, team'],
    ];
@endphp

<div class="pb-6">
<x-tri-layout title="Your sites"
              subtitle="Open a site to edit and run it, or create a new one. New here? Press “How to use” for a step-by-step guide."
              :labels="['📊 Overview', '🌐 Sites', '✅ Get started']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$dispatch('show-intro')" @click="window.scrollTo({ top: 0, behavior: 'smooth' })" class="{{ $pillOutline }}">
                <svg class="w-4 h-4" style="color:var(--primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Introduction
            </button>
            <a href="#how-to-use" @click.prevent="$dispatch('open-how-to')" class="{{ $pillOutline }}">
                <svg class="w-4 h-4" style="color:var(--primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.25v13m0-13C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25m0-13C13.17 5.48 14.75 5 16.5 5c1.75 0 3.33.48 4.5 1.25v13C19.83 18.48 18.25 18 16.5 18c-1.75 0-3.33.48-4.5 1.25"/></svg>
                How to use
            </a>
            <button type="button" wire:click="$set('showCreate', true)"
                    class="inline-flex items-center gap-1.5 min-h-[40px] px-5 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New site
            </button>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: the account at a glance ══ --}}
    <x-slot:rail>
        <div class="grid grid-cols-2 gap-3">
            <x-tile accent="ink" wide :value="$room['used'].' / '.($room['limit'] === null ? '∞' : $room['limit'])" label="Sites on your plan"
                    :sub="$room['plan']" icon="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18" href="{{ route('account.subscription') }}" />
            <x-tile accent="lime" :value="$liveCount" label="Live sites" :sub="'of '.$all->count()" icon="M5 13l4 4L19 7" />
            <x-tile accent="sky" :value="$all->sum('pages_count')" label="Pages" sub="across sites" icon="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
            <x-tile accent="lavender" :value="$all->sum('contacts_count')" label="Contacts" sub="in your CRM" icon="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0z" />
            <x-tile accent="cocoa" :value="$pct.'%'" label="Setup" :sub="$progress['done'].' of '.$progress['total'].' steps'" icon="M9 12l2 2 4-4M12 3a9 9 0 100 18 9 9 0 000-18z" />
            @if ($sub)
                <x-tile accent="rose" wide :value="$sub->onTrial() && ! $sub->trialExpired() ? $sub->trialDaysLeft().' days' : ($sub->trialExpired() ? 'Ended' : ($sub->tier()['name'] ?? 'Plan'))"
                        :label="$sub->onTrial() || $sub->trialExpired() ? 'Free trial' : 'Your plan'"
                        :sub="$sub->trialExpired() ? 'choose a plan' : ($sub->onTrial() ? 'left' : 'active')"
                        icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" href="{{ route('account.subscription') }}" />
            @endif
        </div>
    </x-slot:rail>

    {{-- ══ CENTER: the sites, then how to use Olux ══ --}}
    <div class="space-y-6">

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach(['all' => 'All', 'active' => 'With pages', 'recent' => 'This week'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')"
                    class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors
                           {{ $filter === $key ? 'border-transparent' : 'border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-300' }}"
                    @if ($filter === $key) style="background:var(--foreground);color:var(--background)" @endif>
                {{ $label }}
            </button>
        @endforeach
        @if ($search !== '')
            <span class="text-[12.5px] text-gray-500 dark:text-gray-400">Results for “{{ $search }}”</span>
        @endif
    </div>

    {{-- ── Create modal ── --}}
    @if($showCreate)
    @php
        $base = (string) config('publishing.subdomain_base') ?: 'oluxstudio.com';
        $field = 'w-full rounded-xl border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.04] px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[color-mix(in_srgb,var(--primary)_35%,transparent)] focus:border-transparent';
        $label = 'block text-[12.5px] font-bold text-gray-700 dark:text-gray-200 mb-1.5';
        $err = 'text-[12px] font-semibold text-rose-500 mt-1.5';
    @endphp
    <x-lightbox close="closeCreate" max-width="max-w-2xl" icon="🌐" title="Create a site"
                subtitle="Tell us about the business — you'll choose a template, a domain and edit content next.">

        {{-- Plan room: how many sites this plan allows --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl px-4 py-2.5 mb-5 text-[13px]
                    {{ $room['can'] ? 'bg-gray-50 dark:bg-white/[0.04] text-gray-600 dark:text-gray-300' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200' }}">
            <span><b>{{ $room['plan'] }}</b> · {{ $room['used'] }} of {{ $room['limit'] === null ? 'unlimited' : $room['limit'] }} {{ Str::plural('site', (int) ($room['limit'] ?? 2)) }} used</span>
            <a href="{{ route('account.subscription') }}" class="ml-auto font-bold hover:underline" style="color:var(--primary)">{{ $room['can'] ? 'See plans' : 'Upgrade' }} →</a>
        </div>

        @if (! $room['can'])
            {{-- At the limit: creating becomes an upgrade --}}
            <div class="text-center py-6">
                <span class="mx-auto w-14 h-14 rounded-2xl grid place-items-center text-2xl bg-amber-100 dark:bg-amber-500/15">⬆</span>
                <h3 class="mt-3 text-lg font-extrabold text-gray-900 dark:text-white">
                    {{ $room['expired'] ? 'Your free trial has ended' : 'Your plan is full' }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    {{ $room['expired'] ? 'Pick a plan to keep creating and managing sites — nothing you built has been deleted.' : 'The '.$room['plan'].' plan includes '.$room['limit'].' '.Str::plural('site', (int) $room['limit']).'. Upgrade to add another.' }}
                </p>
                <a href="{{ route('account.subscription') }}" class="inline-flex mt-5 px-5 py-2.5 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Compare plans</a>
            </div>
        @else
        <form wire:submit="create" id="create-site-form" class="space-y-5">
            {{-- 1 · Name + web address --}}
            <div>
                <label for="site-business" class="{{ $label }}">Business or site name</label>
                <input id="site-business" wire:model.live.debounce.400ms="form.owner" type="text" autofocus autocomplete="organization" placeholder="e.g. Grace Way Church" class="{{ $field }}">
                @error('form.owner') <p class="{{ $err }}">{{ $message }}</p> @enderror

                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-[12.5px]" x-data="{ edit: @js($addressEdited) }">
                    <span class="text-gray-500 dark:text-gray-400">Web address</span>
                    <template x-if="! edit">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="font-mono font-semibold text-gray-900 dark:text-white truncate">{{ $form->name ?: 'your-name' }}.{{ $base }}</span>
                            <button type="button" x-on:click="edit = true" class="font-bold hover:underline" style="color:var(--primary)">Change</button>
                        </span>
                    </template>
                    <template x-if="edit">
                        <span class="flex items-center gap-1 flex-1 min-w-[14rem]">
                            <input wire:model.live.debounce.400ms="form.name" type="text" class="{{ $field }} !py-1.5 font-mono" placeholder="your-name">
                            <span class="font-mono text-gray-500 shrink-0">.{{ $base }}</span>
                        </span>
                    </template>
                    @if ($available === true)
                        <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">✓ available</span>
                    @elseif ($available === false)
                        <span class="inline-flex items-center gap-1 font-bold text-rose-500">✕ taken or not allowed</span>
                    @endif
                </div>
                @error('form.name') <p class="{{ $err }}">{{ $message }}</p> @enderror
                <p class="text-[11.5px] text-gray-400 mt-1">Free and live straight away. Connect your own domain (or buy one) when you go live.</p>
            </div>

            {{-- 2 · Kind of business --}}
            <div>
                <p class="{{ $label }}">What kind of business? <span class="font-normal text-gray-400">— sets up the right pages and tools</span></p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach ($businessTypes as $key => $meta)
                        <label wire:key="type-{{ $key }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl border cursor-pointer text-[13px] font-semibold transition-colors
                                      {{ $type === $key ? 'text-gray-900 dark:text-white' : 'border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:border-gray-300 bg-white dark:bg-[#1d1e2a]' }}"
                               @if ($type === $key) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 9%, transparent)" @endif>
                            <input type="radio" wire:model.live="type" value="{{ $key }}" class="sr-only">
                            <span class="text-lg leading-none">{{ $meta['icon'] ?? '•' }}</span>
                            <span class="truncate">{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>

            {{-- 3 · Description --}}
            <div x-data="{ n: @js(mb_strlen((string) $form->description)) }">
                <label for="site-description" class="{{ $label }}">What is the site for?</label>
                <textarea id="site-description" wire:model.blur="form.description" x-on:input="n = $event.target.value.length" rows="3" maxlength="500"
                          placeholder="e.g. A welcoming church in Blackburn — service times, events, sermons and ways to get involved."
                          class="{{ $field }} resize-none"></textarea>
                <div class="flex justify-between gap-3 mt-1">
                    @error('form.description') <p class="{{ $err }} !mt-0">{{ $message }}</p> @else <p class="text-[11.5px] text-gray-400">Used for search engines, link previews and your AI assistant.</p> @enderror
                    <span class="text-[11.5px] text-gray-400 tabular-nums shrink-0" x-text="n + ' / 500'"></span>
                </div>
            </div>

            {{-- 4 · Contact (optional) --}}
            <div>
                <p class="{{ $label }}">Contact details <span class="font-normal text-gray-400">— optional, shown on the site</span></p>
                <div class="grid sm:grid-cols-2 gap-2.5">
                    <div>
                        <input wire:model.blur="contactEmail" type="email" autocomplete="email" placeholder="hello@yourbusiness.com" class="{{ $field }}" aria-label="Contact email">
                        @error('contactEmail') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model.blur="contactPhone" type="tel" autocomplete="tel" placeholder="+44 20 7946 0000" class="{{ $field }}" aria-label="Contact phone">
                        @error('contactPhone') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-[11.5px] text-gray-400 mt-1">Address, opening hours, logo and socials can be added any time in Site Properties.</p>
            </div>

        </form>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2.5">
                <button type="button" wire:click="closeCreate"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06]">Cancel</button>
                @if ($room['can'])
                    <button type="submit" form="create-site-form" wire:loading.attr="disabled" wire:target="create"
                            class="px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm disabled:opacity-60" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="create">Create site</span>
                        <span wire:loading wire:target="create">Creating…</span>
                    </button>
                @endif
            </div>
        </x-slot:footer>
    </x-lightbox>
    @endif

    {{-- New accounts see the guide first; with several sites it moves below them. --}}
    @if (count($sites) <= 2 && $search === '')
        @include('livewire.partials.how-to-use-olux')
    @endif

    {{-- ── Cards ── --}}
    @if(count($sites) === 0)
        <div class="{{ $panel }} flex flex-col items-center justify-center py-16 px-6 text-center">
            @if($search !== '')
                <span class="w-14 h-14 rounded-2xl grid place-items-center text-2xl bg-gray-100 dark:bg-white/[0.06]">🔍</span>
                <p class="mt-3 text-gray-800 dark:text-gray-100 font-bold text-sm">No sites match “{{ $search }}”</p>
                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Try a different name, web address or description.</p>
            @else
                <span class="w-14 h-14 rounded-2xl grid place-items-center text-2xl" style="background:var(--primary-soft)">🌐</span>
                <h3 class="mt-3 text-xl font-extrabold text-gray-900 dark:text-white">Create your first site</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1 max-w-sm">A site holds your pages, content and customers. It starts with ready-made pages for your kind of business and a free web address.</p>
                <button wire:click="$set('showCreate', true)"
                        class="mt-5 inline-flex items-center gap-2 min-h-[44px] px-6 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Create your first site
                </button>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($sites as $site)
            @php
                $color = $siteAvatarColor($site['name']);
                $initials = strtoupper(substr($site['name'], 0, 2));
                $title = ucwords(str_replace('-', ' ', $site['name']));
                // Live sites show their address; drafts get a short preview link.
                $host = $site['url'] && $site['live'] ? preg_replace('#^https?://#', '', $site['url']) : null;
            @endphp
            <div wire:key="site-{{ $site['id'] }}" class="{{ $panel }} !rounded-3xl p-5 flex flex-col hover:shadow-md transition-shadow">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl grid place-items-center text-white text-sm font-bold shrink-0" style="background:{{ $color }}">{{ $initials }}</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-[15px] font-extrabold text-gray-900 dark:text-white leading-snug truncate">{{ $title }}</h3>
                        @if ($host)
                            <a href="{{ $site['url'] }}" target="_blank" rel="noopener" class="block text-[12.5px] text-gray-500 dark:text-gray-400 truncate hover:underline">{{ $host }} ↗</a>
                        @elseif ($site['url'])
                            <a href="{{ $site['url'] }}" target="_blank" rel="noopener" class="block text-[12.5px] text-gray-500 dark:text-gray-400 hover:underline">Preview ↗</a>
                        @endif
                    </div>
                    <span class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full {{ $site['live'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300' }}">
                        {{ $site['live'] ? '● Live' : 'Not live' }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-center">
                    @foreach ([['Pages', $site['pages_count']], ['Contacts', $site['contacts_count'] ?? 0]] as [$dt, $dd])
                        <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] py-2">
                            <dd class="text-[16px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $dd }}</dd>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">{{ $dt }}</dt>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-3 text-[11.5px] text-gray-400">
                    Created {{ \Carbon\Carbon::parse($site['created_at'])->diffForHumans() }}
                    @if (empty($site['is_owner'])) · owned by {{ $site['owner'] }} @endif
                </p>

                <div class="mt-4 flex items-center gap-2">
                    <button type="button" wire:click="selected('{{ $site['id'] }}')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 min-h-[40px] rounded-full text-[13px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                        Open dashboard <span aria-hidden="true">→</span>
                    </button>
                    <a href="{{ url($site['name'].'/connect') }}" class="{{ $pillOutline }} !min-h-[40px] !text-[13px]" title="Edit the live site">
                        <x-icons.pencil class="w-3.5 h-3.5" /> Edit site
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    @if (count($sites) > 2 || $search !== '')
        @include('livewire.partials.how-to-use-olux')
    @endif

    </div>

    {{-- ══ RIGHT rail: get started + plan + related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Get started</p>
                <span class="text-[12px] font-bold text-gray-500 dark:text-gray-400">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                <div class="h-full rounded-full" style="width: {{ $pct }}%; background:var(--primary)"></div>
            </div>
            <ul class="mt-4 space-y-2.5">
                @foreach ($steps as $n => $step)
                    <li class="flex items-start gap-2.5">
                        <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-[11px] font-bold"
                              style="{{ $step['done'] ? 'background:#16a34a;color:#fff' : 'background:var(--foreground);color:var(--background)' }}">{!! $step['done'] ? '&#10003;' : $n + 1 !!}</span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold {{ $step['done'] ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white' }}">{{ $step['label'] }}</span>
                            @unless ($step['done'])<span class="block text-[11.5px] text-gray-500 dark:text-gray-400 leading-snug">{{ $step['description'] }}</span>@endunless
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($nextStep)
                @if ($nextStep['key'] === 'create_site')
                    <button type="button" wire:click="$set('showCreate', true)" class="mt-4 w-full min-h-[42px] rounded-full text-[13px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Next: {{ $nextStep['cta_label'] }}</button>
                @elseif ($nextStep['cta_url'])
                    <a href="{{ $nextStep['cta_url'] }}" class="mt-4 flex items-center justify-center w-full min-h-[42px] rounded-full text-[13px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Next: {{ $nextStep['cta_label'] }}</a>
                @endif
            @else
                <p class="mt-4 text-[12.5px] font-semibold text-emerald-600 dark:text-emerald-400">All set — your site is live 🎉</p>
            @endif
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="text-[15px] font-bold">Good to know</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 leading-relaxed list-disc pl-4">
                <li>Every site gets a free web address the moment you create it.</li>
                <li>Changing template keeps your pages, content and contacts.</li>
                <li>Invite your team from a site’s Team page — each person gets a role.</li>
                <li>Questions? Write to <b>{{ config('mail.from.address') }}</b> and a real person will help.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['Getting started guide', 'every step, plans and answers', route('how-it-works')],
                ['Your subscription', $room['plan'].' · '.$room['used'].' of '.($room['limit'] === null ? 'unlimited' : $room['limit']).' sites', route('account.subscription')],
                ['Template gallery', 'browse every design', route('templates')],
                ['Account settings', 'profile, password, security', route('settings')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
</div>
