@php
    use App\Support\Money;
    $order = array_flip(collect(config('plans.tiers'))->sortBy('order')->keys()->all());
    $currentRank = $order[$sub->plan] ?? 0;

    // Site-theme accents (same palette as <x-tile>): solid swatch + its ink text.
    $accents = [
        'lime'     => ['solid' => '#d9f068', 'ink' => '#2b3110', 'base' => '#f4f6e4'],
        'lavender' => ['solid' => '#d7c3f5', 'ink' => '#33245c', 'base' => '#f1ecf9'],
        'cocoa'    => ['solid' => '#e6d6c6', 'ink' => '#4a3628', 'base' => '#f5efe7'],
        'sky'      => ['solid' => '#bfdcf7', 'ink' => '#173a5e', 'base' => '#e9f1fb'],
        'primary'  => ['solid' => '#f97316', 'ink' => '#ffffff', 'base' => '#fdeadb'],
    ];
    $accentOf = fn ($t) => $accents[$t['accent'] ?? 'lime'] ?? $accents['lime'];

    $tier = $sub->tier();
    $limits = $tier['limits'] ?? [];
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $bytes = fn (int $b) => $b >= 1073741824 ? round($b / 1073741824, 1).' GB' : ($b >= 1048576 ? round($b / 1048576, 1).' MB' : max(0, round($b / 1024)).' KB');
    $mb = fn ($v) => $v === null ? 'Unlimited' : ($v >= 1024 ? round($v / 1024, 1).' GB' : $v.' MB');
    $tokens = fn ($n) => $n >= 1000000 ? round($n / 1000000, 1).'M' : ($n >= 1000 ? round($n / 1000).'k' : (string) $n);
    $sitesLimit = $limits['sites'] ?? null;
    $storageLimit = $sub->storageLimitBytes();
    $currentPrice = $sub->priceFor($sub->plan);
    $status = $sub->trialExpired() ? 'Trial ended' : ($sub->onTrial() ? 'Free trial' : ucfirst($sub->status ?? 'active'));
    $pct = fn ($used, $limit) => $limit ? min(100, round($used / $limit * 100)) : 0;
@endphp
<div>
<x-tri-layout title="Your subscription" subtitle="Compare plans, switch any time — changes apply straight away."
    :labels="['📊 Usage', '💳 Plans', '🧾 Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <a href="{{ $backUrl ?: route('home') }}" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">
            ← Back
        </a>
    </x-slot:header>

    {{-- ══ LEFT rail: where the account stands ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$tier['name'] ?? 'Free trial'" label="Your plan"
                :sub="$sub->trialExpired() ? 'trial ended' : ($sub->onTrial() ? $sub->trialDaysLeft().' '.Str::plural('day', $sub->trialDaysLeft()).' left' : ($sub->plan === 'trial' ? 'free' : Money::format($currentPrice, 'gbp').'/mo'))"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        <x-tile accent="sky" :value="$siteCount.' / '.($sitesLimit === null ? '∞' : $sitesLimit)" label="Sites" sub="used"
                :href="route('home')"
                icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-tile accent="lavender" :value="$bytes($storageUsed)" label="Storage used" :sub="'of '.$mb($sub->storageLimitMb())"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
        <x-tile accent="lime" :value="$tokens($aiUsed)" label="AI this month" :sub="$aiLimit === null ? 'no cap' : 'of '.$tokens($aiLimit)"
                icon="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
        <x-tile accent="cocoa" :value="$sub->allowsPremium() ? 'Included' : 'Not included'" label="Premium add-ons" :sub="$sub->allowsPremium() ? 'on plan' : 'upgrade'"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        @php $freeDomain = $limits['free_domain'] ?? null; @endphp
        <x-tile accent="rose" wide :value="$freeDomain ? ($freeDomain === 'any' ? 'Any domain' : '.'.$freeDomain) : 'Not included'" label="Free domain" :sub="$freeDomain ? 'first year on us' : 'buy one on Go live'"
                icon="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER: the plans ══ --}}
    <div class="@container">
        @if($sub->trialExpired())
            <div class="mb-4 rounded-2xl px-5 py-3.5 text-sm font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-100 dark:border-rose-500/20">
                Your free trial has ended, so your account now follows the Free plan: no own domain, {{ config('plans.tiers.free.limits.bookings_month', 20) }} bookings a month and no invoices.
                Pick a plan to unlock everything again — nothing you built has been deleted.
            </div>
        @endif

        @if($blocker)
            <div class="mb-4 rounded-2xl px-5 py-3.5 text-sm font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-500/20">
                {{ $blocker }}
            </div>
        @endif

        {{-- Plans — the same line-up, order and wording as the public site's Pricing section --}}
        <div class="mb-5">
            <p class="text-[11px] font-bold uppercase tracking-[.16em]" style="color:var(--primary)">Pricing</p>
            <h2 class="text-2xl sm:text-[28px] font-extrabold tracking-tight text-gray-900 dark:text-white leading-tight mt-1">Start free, grow when you do</h2>
            <p class="text-[13.5px] text-gray-500 dark:text-gray-400 mt-1.5 max-w-2xl">
                Every plan starts with a {{ config('plans.trial_days') }}-day free trial with everything unlocked — no card required.
                Paid plans are billed monthly and you pay right here on this page; switch or cancel any time.
            </p>
        </div>

        <div class="grid grid-cols-1 @xl:grid-cols-2 @4xl:grid-cols-3 gap-4 pt-3">
        @foreach($tiers as $key => $t)
        @php
            $isCurrent = $sub->plan === $key;
            $isUpgrade = ($order[$key] ?? 0) > $currentRank;
            $hl = $t['highlight'] ?? false;
            $effective = $sub->priceFor($key);
        @endphp
        <div wire:key="plan-{{ $key }}"
             class="relative flex flex-col rounded-[22px] p-6 bg-white dark:bg-[#1d1e2a] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg
                    {{ $hl || $isCurrent ? 'shadow-lg' : 'shadow-sm border border-gray-100 dark:border-white/[0.07]' }}"
             @if ($hl || $isCurrent) style="box-shadow: 0 0 0 2px var(--primary), 0 12px 30px -12px color-mix(in srgb, var(--primary) 45%, transparent)" @endif>

            @if ($isCurrent)
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full text-[11.5px] font-bold whitespace-nowrap shadow-sm bg-gray-900 text-white dark:bg-white dark:text-gray-900">Your plan</span>
            @elseif ($hl)
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full text-[11.5px] font-bold whitespace-nowrap shadow-sm" style="background:var(--primary);color:var(--on-primary)">Most popular</span>
            @endif

            <h3 class="text-[22px] font-extrabold leading-tight" style="color:var(--primary)">{{ $t['name'] }}</h3>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mt-1 min-h-[2.4rem]">{{ $t['tagline'] }}</p>

            <div class="mt-3 flex items-baseline gap-1.5 flex-wrap">
                @if ($key === 'trial' || $effective === 0)
                    <span class="text-[30px] font-extrabold tracking-tight text-gray-900 dark:text-white">Free</span>
                    @if ($key === 'trial')<span class="text-[12px] text-gray-500 dark:text-gray-400">for {{ config('plans.trial_days') }} days</span>@endif
                @else
                    @if (! empty($t['price_prefix']))<span class="text-[13px] font-bold text-gray-500 dark:text-gray-400">{{ $t['price_prefix'] }}</span>@endif
                    <span class="text-[30px] font-extrabold tracking-tight text-gray-900 dark:text-white">{{ Money::format($effective, 'gbp') }}</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400">/month</span>
                @endif
            </div>
            @if ($sub->hasOverride($key))
                <p class="text-[11.5px] font-bold text-emerald-600 dark:text-emerald-400">Your agreed price</p>
            @elseif (! empty($t['annual_price_cents']) && $key !== 'trial')
                <p class="text-[11.5px] text-gray-500 dark:text-gray-400">or £{{ number_format($t['annual_price_cents'] / 100) }}/year — two months free</p>
            @endif

            <ul class="mt-4 space-y-2 text-[13px] text-gray-700 dark:text-gray-200 flex-1">
                @foreach ($t['features'] as $f)
                    <li class="flex gap-2"><span class="font-bold shrink-0" style="color:var(--primary)">✓</span><span>{{ $f }}</span></li>
                @endforeach
            </ul>

            <div class="mt-5 space-y-2">
                @if ($isCurrent)
                    <span class="flex items-center justify-center w-full min-h-[46px] rounded-xl text-[14px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">
                        {{ $sub->onTrial() ? 'Active · '.$sub->trialDaysLeft().' '.Str::plural('day', $sub->trialDaysLeft()).' left' : 'Current plan' }}
                    </span>
                @elseif ($key === 'trial')
                    <span class="flex items-center justify-center w-full min-h-[46px] rounded-xl text-[13px] font-semibold border border-gray-200 dark:border-white/[0.1] text-gray-400">Starts automatically</span>
                @else
                    <button type="button" wire:click="viewPlan('{{ $key }}')"
                            class="flex items-center justify-center w-full min-h-[46px] rounded-xl text-[14px] font-bold transition-transform hover:scale-[1.01]
                                   {{ $hl ? 'shadow-sm' : 'border-2 border-gray-900 dark:border-white/80 bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white' }}"
                            @if ($hl) style="background:var(--primary);color:var(--on-primary)" @endif>
                        {{ $sub->onTrial() || $sub->trialExpired() ? 'Get started' : ($isUpgrade ? 'Upgrade' : 'Switch') }}
                    </button>
                @endif
                <button type="button" wire:click="viewPlan('{{ $key }}', 'specs')"
                        class="block w-full text-center text-[12px] font-semibold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white underline-offset-2 hover:underline">
                    View all specs for {{ $t['name'] }}
                </button>
            </div>
        </div>
        @endforeach
        </div>

        {{-- Compare plans: every spec, side by side — built from the limits the app enforces (App\Support\PlanSpecs) --}}
        @php
            $cols = collect($tiers)->keys()->values();
            $specCols = $cols->mapWithKeys(fn ($k) => [$k => \App\Support\PlanSpecs::flat($k, $tiers[$k])]);
            $specSections = [];
            foreach ($specCols as $k => $sections) {
                foreach ($sections as $section => $rows) {
                    foreach (array_keys($rows) as $label) { $specSections[$section][$label] = true; }
                }
            }
        @endphp
        <section id="compare-plans" class="mt-8 scroll-mt-24" aria-labelledby="compare-plans-title">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                <h2 id="compare-plans-title" class="text-lg font-extrabold text-gray-900 dark:text-white">Compare every spec</h2>
                <p class="text-[12px] text-gray-500 dark:text-gray-400">✓ included · ✕ not included</p>
            </div>
            <div class="{{ $panel }} overflow-x-auto">
                <table class="w-full min-w-[52rem] text-[12.5px] text-left">
                    <thead>
                        <tr class="border-b-2 border-gray-100 dark:border-white/[0.08]">
                            <th scope="col" class="sticky left-0 z-10 bg-white dark:bg-[#1d1e2a] p-3.5 w-[11rem]"><span class="sr-only">Spec</span></th>
                            @foreach($cols as $k)
                                <th scope="col" class="p-3.5 align-bottom {{ $sub->plan === $k ? 'bg-[color-mix(in_srgb,var(--primary)_7%,transparent)]' : '' }}">
                                    <span class="block text-[14px] font-extrabold text-gray-900 dark:text-white">{{ $tiers[$k]['name'] }}</span>
                                    <span class="block text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">
                                        {{ ! empty($tiers[$k]['price_prefix']) ? $tiers[$k]['price_prefix'].' ' : '' }}{{ $sub->priceFor($k) === 0 ? 'Free' : Money::format($sub->priceFor($k), 'gbp').'/mo' }}
                                    </span>
                                    @if($sub->plan === $k)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9.5px] font-extrabold uppercase tracking-wider" style="background:var(--primary);color:var(--on-primary)">Current</span>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($specSections as $section => $labels)
                            <tr><th colspan="{{ $cols->count() + 1 }}" scope="colgroup" class="sticky left-0 px-3.5 pt-4 pb-1.5 text-[10.5px] font-extrabold uppercase tracking-[.14em] text-left" style="color:var(--primary)">{{ $section }}</th></tr>
                            @foreach(array_keys($labels) as $label)
                                <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                                    <th scope="row" class="sticky left-0 z-10 p-3.5 font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-[#1d1e2a] shadow-[1px_0_0_rgba(0,0,0,0.05)]">{{ $label }}</th>
                                    @foreach($cols as $k)
                                        @php $cell = $specCols[$k][$section][$label] ?? null; @endphp
                                        <td class="p-3.5 text-gray-600 dark:text-gray-300 {{ $sub->plan === $k ? 'bg-[color-mix(in_srgb,var(--primary)_7%,transparent)] font-semibold text-gray-900 dark:text-white' : '' }}">
                                            @if (! $cell)
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @elseif ($cell['ok'] === true)
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400">✓</span> {{ $cell['value'] !== 'Included' ? $cell['value'] : '' }}
                                            @elseif ($cell['ok'] === false)
                                                <span class="font-bold text-rose-500">✕</span> <span class="text-gray-400 dark:text-gray-500">{{ $cell['value'] !== 'Not included' ? $cell['value'] : '' }}</span>
                                            @else
                                                {{ $cell['value'] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-center text-[11px] text-gray-400 dark:text-gray-500 mt-6">
            Plans switch instantly. Billing is handled on your account — you can change or cancel at any time.
        </p>
    </div>

    {{-- ══ RIGHT rail: summary + related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Current subscription</p>
            <h3 class="font-display text-xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $tier['name'] ?? 'Free trial' }}</h3>
            <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $tier['tagline'] ?? '' }}</p>
            <dl class="mt-4 space-y-2 text-[13px]">
                @foreach (array_filter([
                    ['Status', $status],
                    ['Price', $sub->plan === 'trial' ? 'Free' : Money::format($currentPrice, 'gbp').' / month'.($sub->hasOverride($sub->plan) ? ' (your price)' : '')],
                    $sub->onTrial() && $sub->trial_ends_at ? [$sub->trialExpired() ? 'Trial ended' : 'Trial ends', $sub->trial_ends_at->format('j M Y')] : null,
                    $sub->started_at && ! $sub->onTrial() ? ['Member since', $sub->started_at->format('j M Y')] : null,
                ]) as [$dt, $dd])
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $dt }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white text-right">{{ $dd }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-4 space-y-3">
                @foreach (array_filter([
                    ['Sites', $siteCount, $sitesLimit, $siteCount.' / '.($sitesLimit === null ? '∞' : $sitesLimit)],
                    ['Storage', $storageUsed, $storageLimit, $bytes($storageUsed).' / '.$mb($sub->storageLimitMb())],
                    ['AI tokens', $aiUsed, $aiLimit, $tokens($aiUsed).' / '.($aiLimit === null ? '∞' : $tokens($aiLimit))],
                ]) as [$ml, $mu, $mlim, $mtxt])
                    <div>
                        <div class="flex justify-between text-[12px] mb-1">
                            <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $ml }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $mtxt }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                            <div class="h-full rounded-full {{ $mlim && $pct($mu, $mlim) >= 90 ? 'bg-rose-500' : '' }}"
                                 style="width: {{ $mlim === null ? 100 : $pct($mu, $mlim) }}%; {{ $mlim && $pct($mu, $mlim) >= 90 ? '' : 'background:var(--primary)' }}; {{ $mlim === null ? 'opacity:.25' : '' }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">How billing works</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 leading-relaxed list-disc pl-4">
                <li>Pay by card right here on the page — your plan starts the moment it goes through.</li>
                <li>Already paying? Switching plans is prorated on your next monthly invoice.</li>
                <li>Switch or cancel any time — nothing you built is deleted.</li>
                <li>Questions? Write to <b>{{ config('mail.from.address') }}</b>.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['Your sites', 'open or create a site', route('home')],
                ['Plans explained', 'what each plan includes', url('/how-it-works#plans')],
                ['Getting started', 'the 5 steps to go live', url('/how-it-works#steps')],
                ['Account settings', 'profile, password, security', route('settings')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>

    {{-- ═══ PLAN DETAIL — the package on one side; its details, the card form, or the welcome on the other ═══ --}}
    @if($viewingPlan && ($vt = config("plans.tiers.{$viewingPlan}")))
    @php
        $vEffective = $sub->priceFor($viewingPlan);
        $vIsCurrent = $sub->plan === $viewingPlan && ! $paidPlan;
        $vIsUpgrade = ($order[$viewingPlan] ?? 0) > $currentRank;
        $vLimit = $vt['limits']['sites'] ?? null;
        $paying = $payPlan === $viewingPlan && $paySecret;
        $vl = $vt['limits'] ?? [];
        $vBoxes = array_key_exists('mailboxes', $vl) ? ($vl['mailboxes'] === null ? 'Custom' : ($vl['mailboxes'] ?: 'None')) : '—';
        $vCal = array_key_exists('staff_calendars', $vl) ? ($vl['staff_calendars'] ?? 'Unlimited') : '—';
        $vFee = array_key_exists('payment_fee_pct', $vl) && $vl['payment_fee_pct'] !== null ? rtrim(rtrim(number_format((float) $vl['payment_fee_pct'], 1), '0'), '.').'%' : '—';
    @endphp
    <x-lightbox close="closePlan" max-width="max-w-4xl" :title="$vt['name'].' plan'" :subtitle="$vt['tagline']">
        <div class="grid md:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] -mx-6 -my-5">
            {{-- Left: the package, as on the public Pricing section --}}
            <div class="p-7 flex flex-col bg-gray-50 dark:bg-white/[0.03] md:border-r border-gray-100 dark:border-white/[0.06]">
                <h3 class="text-[26px] font-extrabold leading-tight" style="color:var(--primary)">{{ $vt['name'] }}</h3>
                <p class="text-[13px] text-gray-600 dark:text-gray-300 mt-1">{{ $vt['tagline'] }}</p>
                <div class="mt-4 flex items-baseline gap-1.5 flex-wrap">
                    @if ($viewingPlan === 'trial' || $vEffective === 0)
                        <span class="text-4xl font-extrabold text-gray-900 dark:text-white">Free</span>
                        @if ($viewingPlan === 'trial')<span class="text-[13px] text-gray-500">for {{ config('plans.trial_days') }} days</span>@endif
                    @else
                        @if (! empty($vt['price_prefix']))<span class="text-sm font-bold text-gray-500">{{ $vt['price_prefix'] }}</span>@endif
                        <span class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ Money::format($vEffective, 'gbp') }}</span>
                        <span class="text-[13px] text-gray-500">/month</span>
                    @endif
                </div>
                @if (! empty($vt['annual_price_cents']) && $viewingPlan !== 'trial' && ! $sub->hasOverride($viewingPlan))
                    <p class="text-[12px] text-gray-500 dark:text-gray-400">or £{{ number_format($vt['annual_price_cents'] / 100) }}/year — two months free</p>
                @endif
                <ul class="mt-5 space-y-2.5 text-[13.5px] text-gray-700 dark:text-gray-200 flex-1">
                    @foreach($vt['features'] as $f)
                        <li class="flex gap-2"><span class="font-bold shrink-0" style="color:var(--primary)">✓</span><span>{{ $f }}</span></li>
                    @endforeach
                </ul>
                <div class="grid grid-cols-3 gap-2 mt-6">
                    @foreach ([
                        ['Sites', $vLimit === null ? 'Unlimited' : $vLimit],
                        ['Storage', $mb($vl['storage_mb'] ?? null)],
                        ['Mailboxes', $vBoxes],
                        ['Calendars', $vCal],
                        ['Payment fee', $vFee],
                        ['Free domain', ($vl['free_domain'] ?? null) ? (($vl['free_domain'] === 'any') ? 'Any, yr 1' : '.'.$vl['free_domain'].', yr 1') : 'No'],
                    ] as [$vLabel, $vValue])
                        <div class="rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] px-3 py-2.5">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ $vLabel }}</p>
                            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mt-0.5 truncate">{{ $vValue }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Right: details → card form → welcome --}}
            <div class="p-7 flex flex-col">
                @if ($paidPlan === $viewingPlan)
                    <div class="flex-1 flex flex-col items-center justify-center text-center py-8">
                        <span class="w-14 h-14 rounded-2xl grid place-items-center bg-emerald-500 text-white text-2xl">✓</span>
                        <h3 class="mt-4 text-xl font-extrabold text-gray-900 dark:text-white">You're on {{ $vt['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs">Everything in the plan is unlocked now. A receipt is on its way to {{ auth()->user()->email }}.</p>
                        <div class="mt-6 flex flex-wrap justify-center gap-2">
                            <a href="{{ route('home') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Go to your sites</a>
                            <button type="button" wire:click="closePlan" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">Close</button>
                        </div>
                    </div>
                @elseif ($paying)
                    <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Payment</p>
                    <div class="mt-2 rounded-xl bg-gray-50 dark:bg-white/[0.04] px-4 py-3 flex items-center justify-between gap-3">
                        <span class="text-[13px] text-gray-600 dark:text-gray-300">{{ $vt['name'] }} · first month<br><span class="text-[11.5px] text-gray-400">then {{ Money::format($vEffective, 'gbp') }} every month — cancel any time</span></span>
                        <span class="text-xl font-extrabold text-gray-900 dark:text-white">{{ Money::format($payAmount ?: $vEffective, 'gbp') }}</span>
                    </div>
                    <div class="mt-4" x-data="planPay(@js(['key' => $stripeKey, 'secret' => $paySecret, 'returnUrl' => route('account.subscription')]))" wire:key="plan-pay-{{ $paySubscription }}">
                        <div wire:ignore>
                            <div x-ref="element" class="min-h-[140px]"></div>
                            <p x-show="loading" class="text-[13px] text-gray-400" aria-live="polite">Loading secure payment form…</p>
                        </div>
                        <p x-show="error" x-text="error" x-cloak role="alert" class="mt-3 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-[13px] font-semibold text-rose-600 dark:text-rose-400"></p>
                        @if ($payError)
                            <p role="alert" class="mt-3 px-4 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-[13px] font-semibold text-amber-700 dark:text-amber-300">{{ $payError }}</p>
                        @endif
                        <button type="button" x-on:click="pay()" :disabled="loading || busy"
                                class="mt-4 w-full min-h-[50px] rounded-xl text-[15px] font-bold shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background:var(--primary);color:var(--on-primary)">
                            <span x-show="! busy">Pay {{ Money::format($payAmount ?: $vEffective, 'gbp') }} and start {{ $vt['name'] }}</span>
                            <span x-show="busy" x-cloak>Processing payment…</span>
                        </button>
                        <div class="mt-2.5 flex items-center justify-between gap-2 text-[11.5px] text-gray-500 dark:text-gray-400">
                            <button type="button" wire:click="cancelPay" class="font-semibold hover:underline">← Back to the plan</button>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Secured by Stripe
                            </span>
                        </div>
                    </div>
                @else
                    <div class="flex gap-1.5 p-1 rounded-full bg-gray-100 dark:bg-white/[0.06] self-start" role="tablist">
                        @foreach (['about' => 'Plan details', 'specs' => 'All specs'] as $tabKey => $tabLabel)
                            <button type="button" role="tab" wire:click="$set('planTab', '{{ $tabKey }}')" aria-selected="{{ $planTab === $tabKey ? 'true' : 'false' }}"
                                    class="px-4 py-1.5 rounded-full text-[12.5px] font-bold transition-colors {{ $planTab === $tabKey ? 'bg-white dark:bg-[#1d1e2a] shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">{{ $tabLabel }}</button>
                        @endforeach
                    </div>
                    @if ($planTab === 'specs')
                        <div class="mt-4 space-y-4">
                            @foreach (\App\Support\PlanSpecs::for($viewingPlan, $vt) as $section => $rows)
                                <div>
                                    <p class="text-[10.5px] font-extrabold uppercase tracking-[.14em] mb-1.5" style="color:var(--primary)">{{ $section }}</p>
                                    <dl class="rounded-2xl border border-gray-100 dark:border-white/[0.06] divide-y divide-gray-100 dark:divide-white/[0.06]">
                                        @foreach ($rows as $r)
                                            <div class="flex items-start justify-between gap-3 px-3.5 py-2">
                                                <dt class="text-[12.5px] text-gray-600 dark:text-gray-300">{{ $r['label'] }}</dt>
                                                <dd class="text-[12.5px] font-bold text-right {{ $r['ok'] === false ? 'text-gray-400' : 'text-gray-900 dark:text-white' }}">
                                                    @if ($r['ok'] === true)<span class="text-emerald-600 dark:text-emerald-400">✓</span>@elseif ($r['ok'] === false)<span class="text-rose-500">✕</span>@endif
                                                    {{ $r['value'] }}
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    @else
                    <p class="mt-4 text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">About this plan</p>
                    <p class="text-[14px] text-gray-600 dark:text-gray-300 leading-relaxed mt-3">{{ $vt['description'] }}</p>
                    <div class="mt-5 rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-4 text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5">
                        <p><b class="text-gray-900 dark:text-white">Billed monthly</b> — pay by card on this page; nothing to install.</p>
                        <p><b class="text-gray-900 dark:text-white">Switch or cancel any time</b> — nothing you built is ever deleted.</p>
                        @if ($sub->onTrial())<p><b class="text-gray-900 dark:text-white">Your trial</b> — {{ $sub->trialDaysLeft() }} {{ Str::plural('day', $sub->trialDaysLeft()) }} left; upgrading now keeps everything you've made.</p>@endif
                    </div>
                    @endif
                    <div class="flex-1"></div>
                    @if ($vIsCurrent)
                        <span class="mt-6 flex items-center justify-center min-h-[50px] rounded-xl text-sm font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">Your current plan</span>
                    @elseif ($viewingPlan !== 'trial')
                        <button wire:click="choose('{{ $viewingPlan }}')" wire:loading.attr="disabled" wire:target="choose"
                                class="mt-6 flex items-center justify-center gap-1.5 min-h-[50px] rounded-xl text-[15px] font-bold shadow-sm transition-transform hover:scale-[1.01] disabled:opacity-60"
                                style="background:var(--primary);color:var(--on-primary)">
                            <span wire:loading.remove wire:target="choose">{{ $sub->onTrial() || $sub->trialExpired() ? 'Get started with' : ($vIsUpgrade ? 'Upgrade to' : 'Switch to') }} {{ $vt['name'] }} — {{ $vEffective === 0 ? 'Free' : Money::format($vEffective, 'gbp').'/month' }}</span>
                            <span wire:loading wire:target="choose">Preparing secure payment…</span>
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </x-lightbox>
    @endif

@script
<script>
    // Stripe Payment Element for the plan's first monthly payment.
    window.planPay = (cfg) => ({
        loading: true, busy: false, error: '', stripe: null, elements: null,
        async init() {
            if (! cfg.key || ! cfg.secret) { this.loading = false; this.error = 'Payments are not set up yet — contact support.'; return; }
            try {
                if (! window.Stripe) await new Promise((res, rej) => { const s = document.createElement('script'); s.src = 'https://js.stripe.com/v3/'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                this.stripe = window.Stripe(cfg.key);
                const css = getComputedStyle(document.documentElement);
                const dark = document.documentElement.classList.contains('dark');
                this.elements = this.stripe.elements({ clientSecret: cfg.secret, appearance: { theme: dark ? 'night' : 'stripe',
                    variables: { colorPrimary: css.getPropertyValue('--primary').trim() || '#f97316', borderRadius: '12px', fontFamily: getComputedStyle(document.body).fontFamily } } });
                const el = this.elements.create('payment', { layout: { type: 'tabs' } });
                el.on('ready', () => { this.loading = false; });
                el.on('loaderror', (e) => { this.loading = false; this.error = e?.error?.message || 'The payment form could not load.'; });
                el.mount(this.$refs.element);
            } catch (e) {
                this.loading = false;
                this.error = 'The payment form could not load. Check your connection and try again.';
            }
        },
        async pay() {
            if (! this.elements || this.busy) return;
            this.busy = true; this.error = '';
            const { error } = await this.stripe.confirmPayment({ elements: this.elements, confirmParams: { return_url: cfg.returnUrl }, redirect: 'if_required' });
            if (error) { this.error = error.message || 'The payment didn\'t go through.'; this.busy = false; return; }
            await $wire.paymentConfirmed();
            this.busy = false;
        },
    });
</script>
@endscript
</div>
