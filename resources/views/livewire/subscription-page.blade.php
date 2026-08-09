@php
    use App\Support\Money;
    $order = array_flip(collect(config('plans.tiers'))->sortBy('order')->keys()->all());
    $currentRank = $order[$sub->plan] ?? 0;
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

    {{-- Flex-wrapped, centered gradient cards --}}
    <div class="flex flex-wrap justify-center gap-6">
        @foreach($tiers as $key => $t)
        @php
            $isCurrent = $sub->plan === $key;
            $isUpgrade = ($order[$key] ?? 0) > $currentRank;
            $hl = $t['highlight'] ?? false;
            $effective = $sub->priceFor($key);
            $grad = "linear-gradient(160deg, {$t['color']}, color-mix(in srgb, {$t['color']}, #0b0b12 55%))";
        @endphp
        <div class="relative w-full sm:w-[290px] flex flex-col rounded-[26px] p-7 text-white shadow-xl overflow-hidden
                    transition-transform duration-200 hover:-translate-y-1 {{ $isCurrent ? 'ring-4 ring-white/70 dark:ring-white/30' : '' }}"
             style="background: {{ $grad }}">

            {{-- soft corner glow --}}
            <span class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none"></span>

            @if($isCurrent)
                <span class="absolute top-5 right-5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/25">Current</span>
            @elseif($hl)
                <span class="absolute top-5 right-5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/25">Popular</span>
            @endif

            <h3 class="text-2xl font-extrabold">{{ $t['name'] }}</h3>
            <p class="text-[12px] text-white/70 mt-1 min-h-[2.25rem]">{{ $t['tagline'] }}</p>

            <ul class="space-y-2.5 text-[13px] text-white/90 mt-5 flex-1">
                @foreach($t['features'] as $f)
                    <li class="flex gap-2.5">
                        <svg class="w-4 h-4 shrink-0 mt-0.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $f }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Price + CTA row (image-2 style) --}}
            <div class="mt-7 flex items-end justify-between gap-3">
                <div class="leading-none">
                    @if($key === 'trial')
                        <span class="text-3xl font-extrabold">Free</span>
                        <span class="block text-[11px] text-white/70 mt-1">{{ config('plans.trial_days') }} days</span>
                    @else
                        <span class="text-3xl font-extrabold">{{ $effective === 0 ? 'Free' : Money::format($effective, 'gbp') }}</span>
                        <span class="block text-[11px] text-white/70 mt-1">
                            per month
                            @if($sub->hasOverride($key)) · <b class="text-emerald-200">your price</b> @endif
                        </span>
                    @endif
                </div>

                @if($isCurrent)
                    <span class="px-4 py-2 rounded-xl text-xs font-bold bg-white/20">{{ $sub->onTrial() ? 'Active' : 'Current' }}</span>
                @elseif($key === 'trial')
                    <span class="px-4 py-2 rounded-xl text-xs font-bold bg-white/10 text-white/60">Auto</span>
                @else
                    <button wire:click="choose('{{ $key }}')" wire:loading.attr="disabled"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#12131b]/80 hover:bg-[#12131b] transition-colors">
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
</div>
