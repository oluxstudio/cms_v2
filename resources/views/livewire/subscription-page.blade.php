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
    <div class="@container max-w-[52rem] mx-auto">
        @if($sub->trialExpired())
            <div class="mb-4 rounded-2xl px-5 py-3.5 text-sm font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-100 dark:border-rose-500/20">
                Your free trial has ended — pick a plan to unlock your sites again. Nothing you built has been deleted.
            </div>
        @endif

        @if($blocker)
            <div class="mb-4 rounded-2xl px-5 py-3.5 text-sm font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-500/20">
                {{ $blocker }}
            </div>
        @endif

        {{-- Current + Popular use the dark "ink" hero style to contrast from the tinted base cards. --}}
        <div class="grid @xl:grid-cols-2 @4xl:grid-cols-3 gap-5">
        @foreach($tiers as $key => $t)
        @php
            $isCurrent = $sub->plan === $key;
            $isUpgrade = ($order[$key] ?? 0) > $currentRank;
            $hl = $t['highlight'] ?? false;
            $effective = $sub->priceFor($key);
            $a = $accentOf($t);
            $contrast = $isCurrent || $hl;                    // stands out from base cards
        @endphp
        <div class="relative w-full flex flex-col rounded-[26px] p-7 shadow-sm overflow-hidden
                    transition-all duration-200 hover:-translate-y-1 hover:shadow-lg
                    {{ $contrast ? 'text-white shadow-lg' : 'text-gray-900 dark:text-white' }}"
             style="{{ $contrast ? 'background:#332433' : 'background:'.$a['base'].';' }} @if(!$contrast) --tw-bg: {{ $a['base'] }} @endif">

            {{-- accent glow / strip --}}
            @if($contrast)
                <span class="absolute -top-12 -right-12 w-44 h-44 rounded-full blur-3xl pointer-events-none" style="background:{{ $a['solid'] }}33"></span>
            @else
                <span class="absolute top-0 inset-x-0 h-1.5" style="background:{{ $a['solid'] }}"></span>
            @endif

            {{-- badges --}}
            @if($isCurrent)
                <span class="absolute top-5 right-5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider" style="background:{{ $a['solid'] }};color:{{ $a['ink'] }}">Current</span>
            @elseif($hl)
                <span class="absolute top-5 right-5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider" style="background:{{ $a['solid'] }};color:{{ $a['ink'] }}">Popular</span>
            @endif

            <h3 class="text-2xl font-extrabold">{{ $t['name'] }}</h3>
            <p class="text-[12px] mt-1 min-h-[2.25rem] {{ $contrast ? 'text-white/70' : 'text-gray-500 dark:text-gray-400' }}">{{ $t['tagline'] }}</p>

            <ul class="space-y-2.5 text-[13px] mt-5 flex-1 {{ $contrast ? 'text-white/90' : 'text-gray-700 dark:text-gray-200' }}">
                @foreach($t['features'] as $f)
                    <li class="flex gap-2.5">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:{{ $contrast ? $a['solid'] : $a['ink'] }}"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $f }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- details link --}}
            <button wire:click="viewPlan('{{ $key }}')"
                    class="mt-4 self-start text-[11px] font-bold underline underline-offset-2 {{ $contrast ? 'text-white/70 hover:text-white' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' }}">
                View full details →
            </button>

            {{-- Price + CTA --}}
            <div class="mt-5 flex items-end justify-between gap-3">
                <div class="leading-none">
                    @if($key === 'trial')
                        <span class="text-3xl font-extrabold">Free</span>
                        <span class="block text-[11px] mt-1 {{ $contrast ? 'text-white/70' : 'text-gray-400' }}">{{ config('plans.trial_days') }} days</span>
                    @else
                        @if(! empty($t['price_prefix']) && $effective > 0)<span class="block text-[11px] font-bold mb-1 {{ $contrast ? 'text-white/70' : 'text-gray-500' }}">{{ $t['price_prefix'] }}</span>@endif
                        <span class="text-3xl font-extrabold">{{ $effective === 0 ? 'Free' : Money::format($effective, 'gbp') }}</span>
                        <span class="block text-[11px] mt-1 {{ $contrast ? 'text-white/70' : 'text-gray-400' }}">
                            per month @if($sub->hasOverride($key)) · <b class="text-emerald-500 dark:text-emerald-300">your price</b> @endif
                        </span>
                        @if(! empty($t['annual_price_cents']) && ! $sub->hasOverride($key))
                            <span class="block text-[11px] mt-1 font-semibold {{ $contrast ? 'text-white/80' : 'text-gray-500 dark:text-gray-400' }}">or £{{ number_format($t['annual_price_cents'] / 100) }}/year · 2 months free</span>
                        @endif
                    @endif
                </div>

                @if($isCurrent)
                    <span class="px-4 py-2 rounded-xl text-xs font-bold" style="background:{{ $a['solid'] }}22;color:{{ $contrast ? '#fff' : $a['ink'] }}">{{ $sub->onTrial() ? 'Active' : 'Current' }}</span>
                @elseif($key === 'trial')
                    <span class="px-4 py-2 rounded-xl text-xs font-bold {{ $contrast ? 'bg-white/10 text-white/60' : 'bg-black/5 text-gray-400' }}">Auto</span>
                @else
                    {{-- Upgrade CTA — high contrast: accent swatch on the ink cards, ink on the tinted cards --}}
                    <button wire:click="choose('{{ $key }}')" wire:loading.attr="disabled"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-sm transition-transform hover:scale-[1.03]"
                            style="{{ $contrast ? 'background:'.$a['solid'].';color:'.$a['ink'] : 'background:#332433;color:#fff' }}">
                        {{ $isUpgrade ? 'Upgrade' : 'Switch' }}
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                @endif
            </div>
        </div>
        @endforeach
        </div>

        {{-- Compare plans: the full line-up side by side (config plans.compare) --}}
        @php
            $compare = (array) config('plans.compare');
            $cols = collect($tiers)->keys()->filter(fn ($k) => collect($compare)->contains(fn ($row) => array_key_exists($k, $row)))->values();
        @endphp
        @if($compare)
            <section class="mt-8" aria-labelledby="compare-plans">
                <h2 id="compare-plans" class="text-lg font-extrabold text-gray-900 dark:text-white mb-3">Compare plans</h2>
                <div class="{{ $panel }} overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-[12.5px] text-left">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.06]">
                                <th scope="col" class="sticky left-0 z-10 bg-white dark:bg-[#1d1e2a] p-3.5 font-bold text-gray-400 w-[9.5rem]"><span class="sr-only">Feature</span></th>
                                @foreach($cols as $k)
                                    <th scope="col" class="p-3.5 align-bottom">
                                        <span class="block text-[14px] font-extrabold text-gray-900 dark:text-white">{{ $tiers[$k]['name'] }}</span>
                                        <span class="block text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">
                                            {{ ! empty($tiers[$k]['price_prefix']) ? $tiers[$k]['price_prefix'].' ' : '' }}{{ ($tiers[$k]['price_cents'] ?? 0) === 0 ? 'Free' : Money::format($sub->priceFor($k), 'gbp').'/mo' }}
                                        </span>
                                        @if($sub->plan === $k)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9.5px] font-extrabold uppercase tracking-wider" style="background:var(--primary);color:var(--on-primary)">Current</span>@endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($compare as $label => $row)
                                <tr class="border-b last:border-0 border-gray-100 dark:border-white/[0.05]">
                                    <th scope="row" class="sticky left-0 z-10 p-3.5 font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-[#1d1e2a] shadow-[1px_0_0_rgba(0,0,0,0.05)]">{{ $label }}</th>
                                    @foreach($cols as $k)
                                        <td class="p-3.5 text-gray-600 dark:text-gray-300 {{ $sub->plan === $k ? 'font-semibold text-gray-900 dark:text-white' : '' }}">{{ $row[$k] ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

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
                <li>Upgrades start straight away; you're taken to a secure card checkout.</li>
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

    {{-- ═══ PLAN DETAIL — selected package one side, full description the other ═══ --}}
    @if($viewingPlan && ($vt = config("plans.tiers.{$viewingPlan}")))
    @php
        $va = $accentOf($vt);
        $vEffective = $sub->priceFor($viewingPlan);
        $vIsCurrent = $sub->plan === $viewingPlan;
        $vIsUpgrade = ($order[$viewingPlan] ?? 0) > $currentRank;
        $vLimit = $vt['limits']['sites'] ?? null;
    @endphp
    <x-lightbox close="closePlan" max-width="max-w-3xl" :title="$vt['name'].' plan'">
        <div class="grid md:grid-cols-2 -mx-6 -my-5">
            {{-- Left: the selected package --}}
            <div class="p-7 text-white flex flex-col" style="background:#332433">
                <h3 class="text-2xl font-extrabold">{{ $vt['name'] }}</h3>
                <p class="text-[12px] text-white/70 mt-1">{{ $vt['tagline'] }}</p>
                <div class="mt-5">
                    @if(! empty($vt['price_prefix']) && $vEffective > 0)<span class="block text-xs font-bold text-white/70 mb-1">{{ $vt['price_prefix'] }}</span>@endif
                    <span class="text-4xl font-extrabold">{{ $viewingPlan === 'trial' ? 'Free' : ($vEffective === 0 ? 'Free' : Money::format($vEffective, 'gbp')) }}</span>
                    <span class="text-xs text-white/60">{{ $viewingPlan === 'trial' ? '/ '.config('plans.trial_days').' days' : '/ month' }}</span>
                </div>
                <ul class="space-y-2.5 text-[13px] text-white/90 mt-6 flex-1">
                    @foreach($vt['features'] as $f)
                        <li class="flex gap-2.5">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:{{ $va['solid'] }}"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ $f }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($vIsCurrent)
                    <span class="mt-6 px-4 py-2.5 rounded-xl text-sm font-bold text-center" style="background:{{ $va['solid'] }};color:{{ $va['ink'] }}">Your current plan</span>
                @elseif($viewingPlan !== 'trial')
                    <button wire:click="choose('{{ $viewingPlan }}')" wire:loading.attr="disabled"
                            class="mt-6 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition-transform hover:scale-[1.02]"
                            style="background:{{ $va['solid'] }};color:{{ $va['ink'] }}">
                        {{ $vIsUpgrade ? 'Upgrade' : 'Switch' }} to {{ $vt['name'] }}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                @endif
            </div>

            {{-- Right: the full description --}}
            <div class="p-7 flex flex-col">
                <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:{{ $va['ink'] }}">About this plan</p>
                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed mt-3">{{ $vt['description'] }}</p>

                @php
                    $vl = $vt['limits'] ?? [];
                    $vBoxes = array_key_exists('mailboxes', $vl) ? ($vl['mailboxes'] === null ? 'Custom' : ($vl['mailboxes'] ?: 'None')) : '—';
                    $vCal = array_key_exists('staff_calendars', $vl) ? ($vl['staff_calendars'] ?? '∞') : '—';
                    $vFee = array_key_exists('payment_fee_pct', $vl) && $vl['payment_fee_pct'] !== null ? rtrim(rtrim(number_format((float) $vl['payment_fee_pct'], 1), '0'), '.').'%' : '—';
                @endphp
                <div class="grid grid-cols-2 gap-3 mt-6">
                    @foreach ([
                        ['Sites', $vLimit === null ? '∞' : $vLimit],
                        ['Storage', $mb($vl['storage_mb'] ?? null)],
                        ['Mailboxes', $vBoxes],
                        ['Staff calendars', $vCal],
                        ['Payment fee', $vFee],
                        ['Free domain', ($vl['free_domain'] ?? null) ? (($vl['free_domain'] === 'any') ? 'Any, year 1' : '.'.$vl['free_domain'].', year 1') : 'No'],
                    ] as [$vLabel, $vValue])
                        <div class="rounded-2xl p-4" style="background:{{ $va['base'] }}">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $vLabel }}</p>
                            <p class="text-xl font-extrabold text-gray-900 mt-1">{{ $vValue }}</p>
                        </div>
                    @endforeach
                </div>
                @if(! empty($vt['annual_price_cents']))
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">Annual: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ Money::format((int) $vt['annual_price_cents'], 'gbp') }}/year</span> — two months free.</p>
                @endif

                <p class="text-xs text-gray-400 mt-5">Best for: <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $vt['tagline'] }}</span></p>
            </div>
        </div>
    </x-lightbox>
    @endif
</div>
