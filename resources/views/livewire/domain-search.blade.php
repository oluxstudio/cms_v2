{{--
  Find your domain — the BUY path of the go-live flow. Labelled search,
  results with a highlighted .co.uk row, "More ideas" chips, a what-happens
  explainer, and a sticky selection bar that continues to the existing
  checkout. Retail prices only (config/domains.php) — never our cost.
--}}
<div>
    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Find your domain</h2>
    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-4">Type your business name. We will show what's free.</p>

    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
        <label for="domain-query" class="bkf-label">Business name or domain</label>
        <form wire:submit="search" class="flex flex-wrap items-center gap-3">
            <input id="domain-query" wire:model="query" type="text" placeholder="janes-salon" required
                   class="bkf-input flex-1 min-w-[220px] !py-2.5">
            <button type="submit" class="fx min-h-[48px] px-6 rounded-xl text-[15px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching…</span>
            </button>
        </form>

        @if ($errorMessage)
            <p class="mt-3 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-[13px] font-semibold text-rose-600 dark:text-rose-400" role="alert">{{ $errorMessage }}</p>
        @endif

        <div wire:loading wire:target="search, searchFor" class="mt-3 text-[13px] text-gray-400">Checking availability…</div>

        {{-- Results --}}
        @if ($results)
            <ul class="mt-4 rounded-xl border border-gray-100 dark:border-white/[0.06] divide-y divide-gray-100 dark:divide-white/[0.05] overflow-hidden" wire:loading.remove wire:target="search, searchFor">
                @foreach ($results as $r)
                    @php
                        $isBest = $r['tld'] === 'co.uk' && $r['available'];
                        $isSelected = $selected === $r['domain'];
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3
                               {{ $isBest ? '' : '' }} {{ $isSelected ? 'ring-2 ring-inset' : '' }}"
                        @if ($isBest || $isSelected) style="{{ $isBest ? 'background:color-mix(in srgb, var(--primary) 7%, transparent);' : '' }}{{ $isSelected ? '--tw-ring-color:var(--primary)' : '' }}" @endif>
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-[14px] font-bold {{ $r['available'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">{{ $r['domain'] }}</span>
                                @if ($isBest)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold" style="background:var(--primary);color:var(--on-primary)">Best for UK businesses</span>
                                @endif
                            </span>
                            <span class="block text-[12px] mt-0.5 {{ $r['available'] ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400' }}">
                                @if ($r['available'])
                                    Available · {{ $r['price_cents'] ? '£'.number_format($r['price_cents'] / 100, 2).' per year' : 'Price shown at checkout' }}
                                @else
                                    {{ $r['taken_here'] ? 'Already on this platform.' : 'Taken. Try one of the ideas below.' }}
                                @endif
                            </span>
                        </span>
                        @if ($r['available'])
                            <button wire:click="select('{{ $r['domain'] }}')"
                                    class="fx min-h-[44px] px-5 rounded-xl text-sm font-bold {{ $isBest || $isSelected ? 'shadow-sm' : 'border-2 border-gray-800 dark:border-white/70 text-gray-900 dark:text-white bg-white dark:bg-transparent' }}"
                                    @if ($isBest || $isSelected) style="background:var(--primary);color:var(--on-primary)" @endif>
                                {{ $isSelected ? 'Selected ✓' : 'Choose' }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- More ideas --}}
            @if ($ideas = $this->suggestions())
                <p class="mt-4 text-[11px] font-bold uppercase tracking-wide text-gray-400">More ideas</p>
                <div class="mt-1.5 flex flex-wrap gap-2">
                    @foreach ($ideas as $idea)
                        <button wire:click="searchFor('{{ $idea }}')"
                                class="fx min-h-[36px] px-3.5 rounded-full text-[13px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200 hover:border-gray-400">
                            {{ $idea }}.co.uk
                        </button>
                    @endforeach
                </div>
            @endif
        @elseif (! $errorMessage)
            <p class="mt-3 text-[13px] text-gray-400">Search to see what's available across .co.uk, .uk, .com and more.</p>
        @endif
    </div>

    {{-- Hosting plan (trial accounts pick one in the same checkout) --}}
    @if ($this->needsPlan() && $results)
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
            <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Hosting plan <span class="font-semibold text-gray-400">— billed monthly with your domain</span></p>
            <div class="grid sm:grid-cols-3 gap-2">
                @foreach ($tiers as $t)
                    <label class="flex items-start gap-2 p-3 rounded-xl border-2 cursor-pointer {{ $plan === $t['key'] ? '' : 'border-gray-200 dark:border-white/[0.08]' }}"
                           @if ($plan === $t['key']) style="border-color:var(--primary);background:color-mix(in srgb, var(--primary) 6%, transparent)" @endif>
                        <input type="radio" wire:model.live="plan" value="{{ $t['key'] }}" class="mt-0.5">
                        <span class="text-[13px]">
                            <span class="block font-bold text-gray-900 dark:text-white">{{ $t['name'] }} · £{{ number_format($t['price_cents'] / 100, 0) }}/mo</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $t['tagline'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- What happens when you buy --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
        <p class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">What happens when you buy</p>
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach ([
                ['Registered in your name', 'You own it, not us.'],
                ['Connected to your site', 'No settings to change.'],
                ['Secure padlock on', 'Usually live in 5 minutes.'],
            ] as $i => [$t, $d])
                <div class="flex items-start gap-2.5">
                    <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-[12px] font-extrabold" style="background:color-mix(in srgb, var(--primary) 12%, transparent);color:var(--primary)">{{ $i + 1 }}</span>
                    <span class="text-[13px]">
                        <span class="block font-bold text-gray-900 dark:text-white">{{ $t }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $d }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent orders for this site --}}
    @foreach ($orders as $o)
        <p class="text-[12px] {{ $o->status === 'failed' ? 'text-rose-500' : 'text-gray-500 dark:text-gray-400' }}">
            <span class="font-mono font-bold">{{ $o->domain }}</span> —
            @if ($o->status === 'registered') {{ $o->type === 'renew' ? 'renewed' : 'registered' }}, expires {{ $o->expires_at?->toFormattedDateString() }}
                @if ($o->type === 'register' && $o->expires_at && $o->expires_at->lt(now()->addDays(60)) && $o->domain === $this->site->domain)
                    <button wire:click="renew('{{ $o->id }}')" class="fx ml-1 px-2 py-0.5 rounded-lg text-[11px] font-bold text-white" style="background:var(--primary)">
                        Renew 1 year — £{{ number_format(app(\App\Services\Domains\DomainPurchase::class)->priceFor($o->domain) / 100, 2) }}
                    </button>
                @endif
            @elseif ($o->status === 'failed') something went wrong after payment — we're on it, contact support if it persists.
            @else {{ $o->status }} @endif
        </p>
    @endforeach

    {{-- Sticky selection bar --}}
    <div class="sticky bottom-3 mt-4 rounded-2xl px-4 py-3 shadow-xl flex flex-wrap items-center justify-between gap-3 bg-[#1c1d29] text-white {{ $selected ? '' : 'opacity-90' }}">
        <span class="min-w-0 text-sm">
            <span class="block text-[10px] font-bold uppercase tracking-wide text-white/50">Selected</span>
            <span class="block font-mono font-bold truncate">{{ $selected ?: 'Choose a domain above' }}</span>
        </span>
        <button wire:click="continueToCheckout" wire:loading.attr="disabled" @disabled(! $selected)
                class="fx min-h-[48px] px-6 rounded-xl text-[15px] font-bold shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
                style="background:var(--primary);color:var(--on-primary)">
            <span wire:loading.remove wire:target="continueToCheckout">Continue: owner details</span>
            <span wire:loading wire:target="continueToCheckout">One moment…</span>
        </button>
    </div>
</div>
