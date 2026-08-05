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
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 items-stretch">
        @foreach($tiers as $key => $t)
        @php
            $isCurrent = $sub->plan === $key;
            $isUpgrade = ($order[$key] ?? 0) > $currentRank;
            $hl = $t['highlight'] ?? false;
        @endphp
        <div class="relative flex flex-col rounded-2xl border p-5 bg-white dark:bg-[#1d1e2a] transition-shadow
                {{ $isCurrent ? 'border-transparent ring-2 shadow-lg' : ($hl ? 'border-indigo-200 dark:border-indigo-500/30 shadow-md' : 'border-gray-100 dark:border-white/[0.06] shadow-sm') }}"
             @if($isCurrent) style="--tw-ring-color: {{ $t['color'] }}" @endif>

            @if($hl && ! $isCurrent)
                <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-600 text-white">Popular</span>
            @endif
            @if($isCurrent)
                <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider text-white" style="background: {{ $t['color'] }}">Current plan</span>
            @endif

            <p class="text-sm font-extrabold" style="color: {{ $t['color'] }}">{{ $t['name'] }}</p>
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 min-h-[2rem]">{{ $t['tagline'] }}</p>

            @php $effective = $sub->priceFor($key); @endphp
            <p class="mt-3 mb-4">
                @if($key === 'trial')
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">Free</span>
                    <span class="text-xs text-gray-400">/ {{ config('plans.trial_days') }} days</span>
                @else
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $effective === 0 ? 'Free' : Money::format($effective, 'gbp') }}</span>
                    <span class="text-xs text-gray-400">/ month</span>
                    @if($sub->hasOverride($key))
                        <span class="block mt-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                            ★ Your price @if($effective < $t['price_cents'])<s class="text-gray-400 font-normal">{{ Money::format($t['price_cents'], 'gbp') }}</s>@endif
                        </span>
                    @endif
                @endif
            </p>

            <ul class="space-y-2 text-[12px] text-gray-600 dark:text-gray-300 flex-1">
                @foreach($t['features'] as $f)
                    <li class="flex gap-2"><span style="color: {{ $t['color'] }}">✓</span>{{ $f }}</li>
                @endforeach
            </ul>

            <div class="mt-5">
                @if($isCurrent)
                    <span class="block w-full py-2 rounded-xl text-center text-xs font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-400">
                        {{ $sub->onTrial() ? 'Active trial' : 'Your plan' }}</span>
                @elseif($key === 'trial')
                    <span class="block w-full py-2 rounded-xl text-center text-xs font-bold bg-gray-50 dark:bg-white/[0.04] text-gray-300 dark:text-gray-600">Starts automatically</span>
                @else
                    <button wire:click="choose('{{ $key }}')" wire:loading.attr="disabled"
                            class="block w-full py-2 rounded-xl text-center text-xs font-bold text-white transition-transform hover:scale-[1.02]"
                            style="background: {{ $t['color'] }}">
                        {{ $isUpgrade ? 'Upgrade' : 'Switch' }} to {{ $t['name'] }}
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
