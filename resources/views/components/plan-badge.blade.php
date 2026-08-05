{{--
    Account plan badge — ALWAYS visible in the header. Shows the current tier
    (amber countdown while on the free trial, rose when expired) and links to
    the subscription page for upgrades.
--}}
@auth
@php
    $sub = auth()->user()->currentSubscription();
    $tier = $sub->tier();
    $trial = $sub->onTrial();
    $expired = $sub->trialExpired();
@endphp
<a href="{{ route('account.subscription') }}" title="Your plan — click to upgrade"
   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold whitespace-nowrap transition-transform hover:scale-[1.03]
          {{ $expired ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300'
              : ($trial ? 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300' : 'text-white') }}"
   @unless($trial || $expired) style="background: {{ $tier['color'] }}" @endunless>
    <span class="w-1.5 h-1.5 rounded-full {{ $expired ? 'bg-rose-500' : ($trial ? 'bg-amber-500 animate-pulse' : 'bg-white/80') }}"></span>
    {{ $sub->badgeLabel() }}
    @if($trial || $expired)<span class="opacity-70">· Upgrade</span>@endif
</a>
@endauth
