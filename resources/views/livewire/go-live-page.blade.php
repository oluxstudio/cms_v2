{{--
  Go live — house tri-layout: page data (left) · content (center) · summary (right).
  Center = the client's journey: stage stepper + tabs (Buy a domain · Connect & DNS ·
  Switch it on). Hosting space (template shell) is prepared automatically.
--}}
<x-tri-layout title="Go live" subtitle="Put this site on your own domain — served by the platform, always showing your latest content." :site-name="$site->name"
    :labels="['📊 Status', '🚀 Go live', 'ℹ️ Summary']">

    <x-slot:header>
        @if ($site->live)
            <span class="flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 text-emerald-500 text-sm font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> LIVE
            </span>
        @else
            <span class="px-3.5 py-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 text-sm font-bold">Offline</span>
        @endif
    </x-slot:header>

    {{-- ══ LEFT rail: the page's data ══ --}}
    <x-slot:rail>
    @php
        $reachable = $site->live && $site->domain && $site->domain_verified_at;
        $domainState = ! $site->domain ? 'None' : ($site->domain_verified_at ? 'Verified' : 'Awaiting DNS');
    @endphp
    <div class="grid grid-cols-2 gap-3">
        {{-- featured, full-width --}}
        <x-tile accent="ink" wide :value="$reachable ? 'LIVE' : 'Offline'" label="Site status"
                icon="M12 3v18m9-9H3m14.5-5.5L5.5 18.5m0-13l13 13"
                style="background:{{ $reachable ? 'var(--primary)' : 'var(--foreground)' }};color:{{ $reachable ? 'var(--on-primary)' : 'var(--background)' }}"
                :sub="$reachable ? 'on '.$site->domain : ($site->live ? 'not reachable yet' : 'not serving visitors')" />
        <x-tile :accent="$site->live ? 'lime' : 'rose'" :value="$site->live ? 'Yes' : 'No'" label="Deployed to server"
                icon="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"
                :sub="$site->live ? 'live' : 'draft'" />
        <x-tile accent="lavender" :value="$domainState" label="Custom domain"
                icon="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18"
                :sub="$site->domain ? null : 'steps 1–2'" />
        <x-tile accent="lime" :value="$site->subdomainHost() ? 'Yes' : '—'" label="Free subdomain"
                icon="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-.928-8.9a4 4 0 015.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"
                :sub="$site->subdomainHost() ? null : 'not set up'" />
        <x-tile accent="sky" :value="$orders->where('status', 'registered')->where('type', 'register')->count()" label="Domains owned"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                :sub="$orders->isEmpty() ? 'none yet' : 'via Olux'" />
        <x-tile accent="rose" :value="$nextExpiry ? ((int) now()->diffInDays($nextExpiry)).'d' : '—'" label="Days to renewal"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                :sub="$nextExpiry ? $nextExpiry->format('j M') : null" />
    </div>
    </x-slot:rail>

{{-- ══ CENTER: one choice, then one path (choose | buy | connect) ══ --}}
<div class="max-w-[52rem] mx-auto">

    {{-- Honest status banner --}}
    @if ($site->live && $site->domain && $site->domain_verified_at)
        <div class="mb-4 px-4 py-3 rounded-2xl border border-emerald-200 dark:border-emerald-500/25 bg-emerald-50/60 dark:bg-emerald-500/[0.06] flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm">
                <span class="font-bold text-emerald-700 dark:text-emerald-400">Your site is LIVE.</span>
                <span class="text-gray-600 dark:text-gray-300">Serving visitors at <a href="http://{{ $site->domain }}" target="_blank" rel="noopener" class="font-bold text-emerald-700 dark:text-emerald-400 hover:underline">{{ $site->domain }}</a></span>
            </div>
            <x-preview-button :href="'http://'.$site->domain" label="Visit site" small />
        </div>
    @endif

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm font-semibold text-rose-600 dark:text-rose-400" role="alert">{{ $errorMessage }}</p>
    @endif

    {{-- ── Stepper: Site ready → Web address → Connected → Live ── --}}
    @if ($checklistProgress['complete'])
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm px-5 py-3 mb-5 flex flex-wrap items-center gap-2">
        <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center bg-emerald-500">
            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </span>
        <span class="text-sm font-bold text-gray-900 dark:text-white">You're live</span>
        <span class="text-sm text-gray-500 dark:text-gray-400">— serving visitors on <span class="font-bold text-gray-800 dark:text-gray-100">{{ $site->domain }}</span>.</span>
    </div>
    @else
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-5">
        <div class="flex items-center gap-3">
            @foreach ($checklist as $step)
                <div class="flex items-center gap-2.5 min-w-0 {{ $loop->last ? '' : 'flex-1' }}">
                    <span class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-[13px] font-extrabold
                        {{ match ($step['state']) {
                            'done' => 'bg-emerald-600 text-white',
                            'working' => 'bg-amber-400 text-white',
                            'active' => 'bg-white dark:bg-transparent',
                            default => 'bg-gray-100 dark:bg-white/[0.08] text-gray-400',
                        } }}"
                        @if ($step['state'] === 'active') style="border:2px solid var(--primary);color:var(--primary)" @endif>
                        @if ($step['state'] === 'done')
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @elseif ($step['state'] === 'working')
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 3a9 9 0 019 9"/></svg>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    <span class="min-w-0 hidden sm:block">
                        <span class="block text-[13px] font-extrabold leading-tight truncate {{ $step['state'] === 'todo' ? 'text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $step['label'] }}</span>
                        <span class="block text-[11px] leading-tight truncate {{ $step['state'] === 'active' ? 'font-semibold' : 'text-gray-400' }}"
                              @if ($step['state'] === 'active') style="color:var(--primary)" @endif
                              title="{{ $step['description'] }}">{{ $step['state'] === 'active' && $step['key'] !== 'template' ? 'You are here' : $step['description'] }}</span>
                    </span>
                    @unless ($loop->last)
                        <span class="flex-1 h-[2.5px] min-w-[16px] rounded-full {{ $step['state'] === 'done' ? 'bg-emerald-600' : 'bg-gray-200 dark:bg-white/[0.08]' }}"></span>
                    @endunless
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ════════ STATE · CHOOSE ════════ --}}
    @if ($pane === 'choose')
        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Get your web address</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-4">This is the address customers type to find you. Pick one option below.</p>

        <div class="grid md:grid-cols-2 gap-4 items-stretch">
            {{-- Buy card --}}
            <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border-2 p-5 flex flex-col shadow-sm" style="border-color:var(--primary)">
                <div class="flex items-start justify-between mb-2">
                    <span class="w-11 h-11 rounded-xl grid place-items-center" style="background:color-mix(in srgb, var(--primary) 12%, transparent);color:var(--primary)">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold" style="background:color-mix(in srgb, var(--primary) 12%, transparent);color:var(--primary-strong)">Easiest</span>
                </div>
                <h3 class="text-[17px] font-extrabold text-gray-900 dark:text-white mb-1">Buy a new domain</h3>
                <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">Search, pick one, and we set everything up for you.</p>
                <ul class="space-y-1.5 text-[13px] text-gray-700 dark:text-gray-200 mb-4">
                    @php
                        $plan = auth()->user()->currentSubscription();
                        $tier = config('plans.tiers.'.$plan->plan, []);
                        $ticks = ['No technical steps', 'Live in about 5 minutes'];
                        if ($tier['domain_included'] ?? false) $ticks[] = '1 domain included on '.($tier['name'] ?? ucfirst($plan->plan));
                    @endphp
                    @foreach ($ticks as $t)
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $t }}</li>
                    @endforeach
                </ul>
                <button wire:click="chooseBuy"
                        @if (filled($site->domain)) data-confirm="Remove {{ $site->domain }} and start again?" @endif
                        class="fx mt-auto w-full min-h-[48px] rounded-xl text-[15px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Find a domain</button>
            </div>

            {{-- Connect card --}}
            <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] p-5 flex flex-col shadow-sm">
                <span class="w-11 h-11 rounded-xl grid place-items-center bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300 mb-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-.928-8.9a4 4 0 015.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"/></svg>
                </span>
                <h3 class="text-[17px] font-extrabold text-gray-900 dark:text-white mb-1">I already own a domain</h3>
                <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">Connect one from GoDaddy, IONOS, 123-Reg or anywhere else.</p>
                <ul class="space-y-1.5 text-[13px] text-gray-700 dark:text-gray-200 mb-4">
                    @foreach (['Takes about 10 minutes', 'Your email keeps working', 'Step-by-step help for your provider'] as $t)
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $t }}</li>
                    @endforeach
                </ul>
                <button wire:click="chooseConnect" class="fx mt-auto w-full min-h-[48px] rounded-xl text-[15px] font-bold bg-white dark:bg-transparent text-gray-900 dark:text-white border-2 border-gray-800 dark:border-white/70">Connect my domain</button>
            </div>
        </div>

        {{-- Status strip: the site already answers somewhere --}}
        <div class="mt-4 px-5 py-4 rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm flex flex-wrap items-center justify-between gap-3">
            <span class="flex items-start gap-2.5 min-w-0">
                <span class="shrink-0 w-2 h-2 rounded-full bg-emerald-500 mt-1.5"></span>
                <span class="min-w-0">
                    <span class="block text-[14px] font-bold text-gray-900 dark:text-white truncate">Your site already works at {{ $site->subdomainHost() ?: 'its preview address' }}</span>
                    <span class="block text-[12.5px] text-gray-500 dark:text-gray-400">Share it now. It will switch to your new address automatically.</span>
                </span>
            </span>
            <a href="{{ $site->publicUrl() }}" target="_blank" rel="noopener" class="fx shrink-0 text-[14px] font-extrabold underline underline-offset-4" style="color:var(--primary-strong)">View site</a>
        </div>

        <p class="mt-4 text-center text-[13.5px] text-gray-600 dark:text-gray-400">
            Not sure? <a href="mailto:{{ $supportMail }}" class="font-extrabold underline underline-offset-4" style="color:var(--primary-strong)">Let us set it up for you</a>
        </p>

    {{-- ════════ STATE · BUY ════════ --}}
    @elseif ($pane === 'buy')
        {{-- On the payment step the panel has its own "Change domain" back button. --}}
        @unless ($ourOrder || $buyStage === 'pay')
            <button wire:click="backToOptions" class="fx inline-flex items-center gap-1.5 mb-3 min-h-[40px] px-3.5 rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to options
            </button>
        @endunless

        @if ($ourOrder)
            {{-- Registered / in-flight through us: status instead of the search --}}
            <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 w-10 h-10 rounded-xl grid place-items-center {{ $ourOrder->status === 'registered' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600' }}">
                        @if ($ourOrder->status === 'registered')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 3a9 9 0 019 9"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white font-mono">{{ $ourOrder->domain }}</p>
                        <p class="text-[13px] text-gray-600 dark:text-gray-300">
                            @if ($ourOrder->status === 'registered')
                                Registered in your name, connected automatically{{ $ourOrder->expires_at ? ' · renews '.$ourOrder->expires_at->format('j M Y') : '' }}.
                            @else
                                Payment received — we're registering it now. This usually takes a few minutes.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @else
            <livewire:domain-search :site="$site" />
        @endif

    {{-- ════════ STATE · CONNECT ════════ --}}
    @else
        <button wire:click="backToOptions" class="fx inline-flex items-center gap-1.5 mb-2 min-h-[40px] px-3.5 rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to options
        </button>
        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Connect your domain</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-4">Three short steps. We check everything for you as you go.</p>

        {{-- Card 1 · Which domain do you own? --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">1 · Which domain do you own?</h3>
            @if ($domainLocked && $site->domain)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="flex-1 min-w-[220px] px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-emerald-300 dark:border-emerald-500/40 text-sm font-bold font-mono text-gray-900 dark:text-gray-100">{{ $site->domain }}</span>
                    <button wire:click="unlockDomain" class="fx min-h-[44px] px-4 rounded-xl border-2 border-gray-800 dark:border-white/70 text-sm font-bold text-gray-900 dark:text-white bg-white dark:bg-transparent">Change</button>
                </div>
                <p class="mt-2 text-[13px] font-semibold text-emerald-600 dark:text-emerald-400">
                    ✓ Found it.{{ $registrar['name'] ? ' Your domain is with '.$registrar['name'].'.' : '' }}
                </p>
            @else
                <label for="own-domain" class="bkf-label">Your domain</label>
                <form wire:submit="saveDomain" class="flex flex-wrap items-center gap-3">
                    <input id="own-domain" wire:model="domain" type="text" placeholder="janes-salon.co.uk" required
                           class="bkf-input flex-1 min-w-[220px] !py-2.5">
                    <button type="submit" class="fx min-h-[48px] px-5 rounded-xl text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="saveDomain">Save domain</span>
                        <span wire:loading wire:target="saveDomain">Checking…</span>
                    </button>
                </form>
                <p class="mt-1.5 text-[12px] text-gray-500 dark:text-gray-400" wire:loading.remove wire:target="saveDomain">Enter it without www — both work once live. We check the domain really exists before saving.</p>
                <p class="mt-1.5 text-[12px] font-semibold" style="color:var(--primary)" wire:loading wire:target="saveDomain" aria-live="polite">Checking that this domain exists…</p>
            @endif
        </div>

        {{-- Card 2 · Point it at your site --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4 {{ $site->domain ? '' : 'opacity-50 pointer-events-none' }}">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">2 · Point it at your site</h3>

            @unless ($dnsAvailable)
                <p class="px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/[0.05] text-sm text-gray-600 dark:text-gray-300">
                    Domain connection is temporarily unavailable. We've been notified and are on it — check back soon.
                </p>
            @else
                @if (config('domains.domain_connect') && $registrar['name'])
                    {{-- TODO: Domain Connect one-click flow --}}
                    <div class="mb-3 px-4 py-3 rounded-xl flex flex-wrap items-center justify-between gap-2" style="background:color-mix(in srgb, var(--primary) 10%, transparent)">
                        <span class="text-[13px] font-bold text-gray-800 dark:text-gray-100">Quickest: connect automatically</span>
                        <button class="fx min-h-[44px] px-4 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Connect with {{ $registrar['name'] }}</button>
                    </div>
                    <p class="text-center text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-3">or add these records yourself</p>
                @else
                    <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">Add these records at your domain provider, then we verify them for you.</p>
                @endif

                <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                     x-data="{ copied: null, copy(v, k) { navigator.clipboard.writeText(v); this.copied = k; setTimeout(() => this.copied = null, 2000) } }">
                    <table class="w-full text-sm hidden sm:table">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                                <th class="px-4 py-2.5">Type</th><th class="px-4 py-2.5">Name</th><th class="px-4 py-2.5">Value</th><th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="font-mono text-[13px] text-gray-800 dark:text-gray-100">
                            @foreach ([
                                ['A', '@', $dnsTarget],
                                ['CNAME', 'www', $site->subdomainHost() ?: ($site->domain ?: 'your-domain.com')],
                                ['TXT', '_olux-verify', $this->verifyToken()],
                            ] as [$rt, $rn, $rv])
                            <tr class="border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                                <td class="px-4 py-2.5 font-bold">{{ $rt }}</td>
                                <td class="px-4 py-2.5">{{ $rn }}</td>
                                <td class="px-4 py-2.5 break-all">{{ $rv }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" @click="copy(@js($rv), '{{ $rt }}')"
                                            class="fx min-h-[36px] px-3 rounded-lg text-[12px] font-sans font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">
                                        <span x-show="copied !== '{{ $rt }}'">Copy</span>
                                        <span x-show="copied === '{{ $rt }}'" x-cloak class="text-emerald-600">Copied</span>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{-- Mobile: stacked rows --}}
                    <div class="sm:hidden divide-y divide-gray-50 dark:divide-white/[0.04]">
                        @foreach ([
                            ['A', '@', $dnsTarget],
                            ['CNAME', 'www', $site->subdomainHost() ?: ($site->domain ?: 'your-domain.com')],
                            ['TXT', '_olux-verify', $this->verifyToken()],
                        ] as [$rt, $rn, $rv])
                        <div class="p-3">
                            <p class="text-[11px] font-bold uppercase text-gray-400">{{ $rt }} · {{ $rn }}</p>
                            <p class="font-mono text-[13px] break-all text-gray-800 dark:text-gray-100 mt-0.5">{{ $rv }}</p>
                            <button type="button" @click="copy(@js($rv), 'm{{ $rt }}')" class="fx mt-1.5 min-h-[44px] px-4 rounded-lg text-[12px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">
                                <span x-show="copied !== 'm{{ $rt }}'">Copy</span><span x-show="copied === 'm{{ $rt }}'" x-cloak class="text-emerald-600">Copied</span>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ $registrar['help'] ?? 'https://www.cloudflare.com/learning/dns/how-to-add-dns-records/' }}" target="_blank" rel="noopener"
                   class="inline-block mt-2.5 text-[13px] font-bold hover:underline" style="color:var(--primary)">
                    Show me where to add these{{ $registrar['name'] ? ' in '.$registrar['name'] : '' }}
                </a>
            @endunless
        </div>

        {{-- Card 3 · We're checking --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4 {{ $site->domain && $dnsAvailable ? '' : 'opacity-50 pointer-events-none' }}"
             @if ($site->domain && $dnsAvailable && ! $this->checksPassed()) wire:poll.45s="runChecks" @endif>
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">3 · We're checking</h3>
                <div class="flex items-center gap-2.5">
                    @if ($lastCheckedAt)
                        <span class="text-[11px] text-gray-400">Checked {{ \Illuminate\Support\Carbon::parse($lastCheckedAt)->diffForHumans(null, true) }} ago</span>
                    @endif
                    <button wire:click="runChecks" class="fx min-h-[36px] px-3 rounded-lg text-[12px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">
                        <span wire:loading.remove wire:target="runChecks">Check now</span>
                        <span wire:loading wire:target="runChecks">Checking…</span>
                    </button>
                </div>
            </div>
            <div class="space-y-2.5" aria-live="polite">
                @foreach ([
                    'records' => 'Records found',
                    'ownership' => 'Confirming you own it',
                    'ssl' => 'Secure padlock (SSL)',
                ] as $ck => $cl)
                    @php $c = $checks[$ck] ?? ['state' => 'waiting', 'hint' => null]; @endphp
                    <div class="flex items-center gap-2.5">
                        <span class="shrink-0 w-5 h-5 rounded-full grid place-items-center {{ $c['state'] === 'done' ? 'bg-emerald-500' : ($c['state'] === 'working' ? '' : 'bg-gray-200 dark:bg-white/[0.12]') }}"
                              @if ($c['state'] === 'working') style="background:color-mix(in srgb, var(--primary) 18%, transparent)" @endif>
                            @if ($c['state'] === 'done')
                                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @elseif ($c['state'] === 'working')
                                <svg class="w-3 h-3 animate-spin" style="color:var(--primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 3a9 9 0 019 9"/></svg>
                            @endif
                        </span>
                        <span class="text-[13.5px] font-bold {{ $c['state'] === 'done' ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' }}">{{ $cl }}</span>
                        @if ($c['hint'])<span class="text-[12px] text-gray-400">· {{ $c['hint'] }}</span>@endif
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[12px] text-gray-500 dark:text-gray-400">You can close this page. We'll email you when it's ready.</p>
            {{-- TODO: hook the verified email into the verification job --}}
        </div>

        {{-- Footer: Go live --}}
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="toggleLive" @disabled(! $site->live && ! $this->checksPassed())
                    class="fx min-h-[52px] px-6 rounded-xl text-[15px] font-bold text-white disabled:opacity-40 disabled:cursor-not-allowed {{ $site->live ? 'bg-gray-500 hover:bg-gray-600' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                {{ $site->live ? 'Take offline' : 'Go live' }}
            </button>
            @if (! $site->live)
                <span class="text-[13px] text-gray-500 dark:text-gray-400">{{ $this->checksPassed() ? 'Your site will go live at '.$site->domain.'.' : 'Unlocks when all 3 checks pass' }}</span>
            @elseif ($site->domain)
                <a href="http://{{ $site->domain }}" target="_blank" rel="noopener" class="text-sm font-bold hover:underline" style="color:var(--primary)">Visit {{ $site->domain }}</a>
            @endif
            <a href="mailto:{{ $supportMail }}" class="ml-auto text-[13px] font-bold text-gray-500 hover:underline">Stuck? Let us do it</a>
        </div>
    @endif
</div>

    {{-- ══ RIGHT rail: page summary + related info from elsewhere in the app ══ --}}
    <x-slot:quick>
        {{-- Site status at a glance — the single source of truth --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Site status</h3>
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $checklistProgress['complete'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.08] dark:text-gray-300' }}">
                    {{ $checklistProgress['done'] }}/{{ $checklistProgress['total'] }} stages
                </span>
            </div>
            <div class="h-[6px] rounded-full bg-gray-100 dark:bg-white/[0.08] overflow-hidden mb-3">
                <div class="h-full rounded-full transition-all" style="width:{{ $checklistProgress['pct'] }}%;background:{{ $checklistProgress['complete'] ? '#22c55e' : 'var(--primary)' }}"></div>
            </div>
            @foreach ([
                ['Domain connected', filled($site->domain), $site->domain ?: 'none'],
                ['DNS verified', (bool) $site->domain_verified_at, $site->domain_verified_at?->diffForHumans() ?? 'not yet'],
                ['Deployed to server', (bool) $site->live, $site->live ? $site->renderTemplateKey().' renderer' : 'draft only'],
                ['Serving visitors', $reachable, $reachable ? 'on '.$site->domain : 'not reachable'],
            ] as [$slabel, $ok, $detail])
                <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="shrink-0 w-4 h-4 rounded-full grid place-items-center {{ $ok ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-white/[0.12]' }}">
                        @if ($ok)<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else<span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>@endif
                    </span>
                    <span class="min-w-0 flex-1 text-[12px] font-bold {{ $ok ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">{{ $slabel }}</span>
                    <span class="shrink-0 text-[10px] text-gray-400 truncate max-w-[45%]">{{ $detail }}</span>
                </div>
            @endforeach
        </div>

        {{-- Where the site answers --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4 space-y-2.5">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Your addresses</h3>
            @if ($site->domain)
                <a href="http://{{ $site->domain }}" target="_blank" rel="noopener" class="fx flex items-center gap-2.5 text-left">
                    <span class="shrink-0 w-2 h-2 rounded-full" style="background:{{ $site->live && $site->domain_verified_at ? '#22c55e' : '#f59e0b' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $site->domain }}</span>
                        <span class="block text-[10px] text-gray-400">custom domain · {{ $site->domain_verified_at ? ($site->live ? 'live' : 'verified, offline') : 'not verified yet' }}</span>
                    </span>
                </a>
            @endif
            @if ($site->subdomainHost())
                <a href="https://{{ $site->subdomainHost() }}" target="_blank" rel="noopener" class="fx flex items-center gap-2.5 text-left">
                    <span class="shrink-0 w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $site->subdomainHost() }}</span>
                        <span class="block text-[10px] text-gray-400">free subdomain · always on</span>
                    </span>
                </a>
            @endif

            {{-- Change the web address — the old one keeps redirecting --}}
            @php($addrBase = (string) config('publishing.subdomain_base'))
            @php($oldNames = $site->aliases()->latest()->pluck('name'))
            @if ($addressOpen)
                <form wire:submit="changeAddress" class="pt-2.5 border-t border-gray-50 dark:border-white/[0.04] space-y-2">
                    <label for="new-address" class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">New web address</label>
                    <div class="flex items-stretch rounded-xl border border-gray-300 dark:border-white/15 bg-white dark:bg-[#15161f] focus-within:border-[color:var(--primary)] overflow-hidden">
                        <input id="new-address" type="text" wire:model.live.debounce.400ms="newAddress" autocomplete="off" autocapitalize="none" spellcheck="false"
                               class="min-w-0 flex-1 px-3 py-2 text-[13px] font-mono bg-transparent border-0 focus:ring-0 text-gray-900 dark:text-white">
                        @if ($addrBase !== '')<span class="shrink-0 grid place-items-center px-2 text-[11.5px] font-mono text-gray-400 bg-gray-50 dark:bg-white/[0.04]">.{{ $addrBase }}</span>@endif
                    </div>
                    @if ($addressError)
                        <p class="text-[11.5px] font-semibold text-rose-500" role="alert">{{ $addressError }}</p>
                    @elseif ($newAddress !== '' && $newAddress !== $site->name)
                        <p class="text-[11.5px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $addrBase !== '' ? $newAddress.'.'.$addrBase : $newAddress }} is available.</p>
                    @endif
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                        Links to <span class="font-mono">{{ $site->subdomainHost() ?: $site->name }}</span> will redirect to the new address, so nothing already shared breaks.
                        Your Site Name doesn’t change.
                    </p>
                    <div class="flex gap-2">
                        <button type="submit" wire:loading.attr="disabled" wire:target="changeAddress"
                                @disabled($addressError !== '' || $newAddress === '' || $newAddress === $site->name)
                                data-confirm="Change this site’s web address to {{ $newAddress }}? Old links will redirect to it."
                                class="fx min-h-[38px] px-3.5 rounded-xl text-[12.5px] font-bold text-white disabled:opacity-50" style="background:var(--primary)">
                            <span wire:loading.remove wire:target="changeAddress">Change address</span>
                            <span wire:loading wire:target="changeAddress">Changing…</span>
                        </button>
                        <button type="button" wire:click="$set('addressOpen', false)"
                                class="fx min-h-[38px] px-3.5 rounded-xl text-[12.5px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">Cancel</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="openAddress"
                        class="fx w-full min-h-[36px] rounded-xl text-[12px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">Change web address</button>
            @endif
            @if ($oldNames->isNotEmpty())
                <p class="text-[10.5px] text-gray-400 leading-snug">Old {{ Str::plural('address', $oldNames->count()) }}, redirecting here: <span class="font-mono">{{ $oldNames->implode(', ') }}</span></p>
            @endif
            <a href="{{ $site->templatePreviewUrl() }}" target="_blank" rel="noopener" class="fx flex items-center gap-2.5 text-left">
                <span class="shrink-0 w-2 h-2 rounded-full bg-sky-400"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $site->live ? 'Live preview' : 'Draft preview' }}</span>
                    <span class="block text-[10px] text-gray-400">platform URL · {{ $site->live ? 'always current' : 'not public — for your eyes' }}</span>
                </span>
            </a>
        </div>

        {{-- Your domains --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Your domains</h3>
                <button wire:click="backToOptions" class="text-[11px] font-semibold text-indigo-500 hover:underline">Options →</button>
            </div>
            @forelse ($orders as $o)
                <div class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="shrink-0 w-2 h-2 rounded-full" style="background:{{ ['registered' => '#22c55e', 'pending' => '#f59e0b', 'paid' => '#f59e0b', 'failed' => '#ef4444'][$o->status] ?? '#9ca3af' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold font-mono text-gray-800 dark:text-gray-100 truncate">{{ $o->domain }}</span>
                        <span class="block text-[10px] text-gray-400">
                            {{ $o->type === 'renew' ? 'renewal' : $o->status }}{{ $o->expires_at ? ' · expires '.$o->expires_at->format('j M Y') : '' }}
                        </span>
                    </span>
                    @if ($o->domain === $site->domain && $o->status === 'registered')
                        <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">in use</span>
                    @endif
                </div>
            @empty
                <p class="text-[11px] text-gray-400 py-2">No domains bought here yet — grab one from the Buy tab.</p>
            @endforelse
        </div>

        {{-- Related elsewhere in the app --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mt-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Edit the site', 'polish content before going live', $site->name.'/connect'],
                ['Marketplace', 'template & features powering the site', $site->name.'/marketplace'],
                ['Analytics', 'traffic once visitors arrive', $site->name.'/analytics'],
                ['Alerts', 'renewal & domain notices land here', $site->name.'/alerts'],
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
