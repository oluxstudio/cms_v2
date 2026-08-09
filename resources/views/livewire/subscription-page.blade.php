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
@endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">

    <div class="text-center mb-10">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Your subscription</h1>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">
            You're on <b class="text-gray-700 dark:text-gray-200">{{ $sub->tier()['name'] }}</b>
            @if($sub->onTrial() && ! $sub->trialExpired()) — {{ $sub->trialDaysLeft() }} {{ Str::plural('day', $sub->trialDaysLeft()) }} left @endif
            @if($sub->trialExpired()) — <span class="text-rose-500 font-semibold">your trial has ended, pick a plan to continue</span> @endif
        </p>
        <p class="inline-flex items-center gap-2 mt-3 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Sites used: {{ $sub->sitesUsage() }}
        </p>
    </div>

    {{-- Flex-wrapped, centered cards — site theme. Current + Popular use the
         dark "ink" hero style to contrast from the tinted base cards. --}}
    <div class="flex flex-wrap justify-center gap-6">
        @foreach($tiers as $key => $t)
        @php
            $isCurrent = $sub->plan === $key;
            $isUpgrade = ($order[$key] ?? 0) > $currentRank;
            $hl = $t['highlight'] ?? false;
            $effective = $sub->priceFor($key);
            $a = $accentOf($t);
            $contrast = $isCurrent || $hl;                    // stands out from base cards
        @endphp
        <div class="relative w-full sm:w-[290px] flex flex-col rounded-[26px] p-7 shadow-sm overflow-hidden
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
                        <span class="text-3xl font-extrabold">{{ $effective === 0 ? 'Free' : Money::format($effective, 'gbp') }}</span>
                        <span class="block text-[11px] mt-1 {{ $contrast ? 'text-white/70' : 'text-gray-400' }}">
                            per month @if($sub->hasOverride($key)) · <b class="text-emerald-500 dark:text-emerald-300">your price</b> @endif
                        </span>
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

    <p class="text-center text-[11px] text-gray-400 dark:text-gray-500 mt-8">
        Plans switch instantly. Billing is handled on your account — you can change or cancel at any time.
    </p>

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

                <div class="grid grid-cols-2 gap-3 mt-6">
                    <div class="rounded-2xl p-4" style="background:{{ $va['base'] }}">
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Sites</p>
                        <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ $vLimit === null ? '∞' : $vLimit }}</p>
                    </div>
                    <div class="rounded-2xl p-4" style="background:{{ $va['base'] }}">
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Premium modules</p>
                        <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ ($vt['limits']['premium'] ?? false) ? 'Yes' : 'No' }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-5">Best for: <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $vt['tagline'] }}</span></p>
            </div>
        </div>
    </x-lightbox>
    @endif
</div>
