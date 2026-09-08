<div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-indigo-100 dark:border-indigo-500/20 shadow-sm p-5 mb-4">
    <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">Don't have a domain yet? Buy one here</h2>
    <p class="text-xs text-gray-400 mb-3">We register it, point it at your site and switch it on — no DNS to touch.</p>

    <form wire:submit="search" class="flex flex-wrap items-center gap-3 mb-3">
        <input wire:model="query" type="text" placeholder="e.g. janes-salon" required
               class="flex-1 min-w-[220px] px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
            <span wire:loading.remove wire:target="search">Search</span>
            <span wire:loading wire:target="search">Searching…</span>
        </button>
    </form>

    @if ($errorMessage)
        <p class="mb-3 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-xs text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    @if ($this->needsPlan() && $results)
        <div class="mb-3">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Hosting plan (billed monthly with your domain)</p>
            <div class="grid sm:grid-cols-3 gap-2">
                @foreach ($tiers as $t)
                    <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer {{ $plan === $t['key'] ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-500/10' : 'border-gray-200 dark:border-white/[0.08]' }}">
                        <input type="radio" wire:model="plan" value="{{ $t['key'] }}" class="mt-0.5">
                        <span class="text-xs">
                            <span class="block font-bold text-gray-900 dark:text-white">{{ $t['name'] }} · £{{ number_format($t['price_cents'] / 100, 0) }}/mo</span>
                            <span class="text-gray-400">{{ $t['tagline'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @if ($results)
        <ul class="divide-y divide-gray-100 dark:divide-white/[0.05] rounded-xl border border-gray-100 dark:border-white/[0.06]">
            @foreach ($results as $r)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <span class="font-mono {{ $r['available'] ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400 line-through' }}">{{ $r['domain'] }}</span>
                    @if ($r['available'])
                        <span class="flex items-center gap-3">
                            <span class="text-xs text-gray-500">£{{ number_format($r['price_cents'] / 100, 2) }}/yr</span>
                            <button wire:click="buy('{{ $r['domain'] }}')" wire:loading.attr="disabled"
                                    class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Buy &amp; go live</button>
                        </span>
                    @else
                        <span class="text-xs text-gray-400">{{ $r['taken_here'] ? 'Already on this platform' : 'Taken' }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @foreach ($orders as $o)
        <p class="mt-3 text-xs {{ $o->status === 'failed' ? 'text-rose-500' : 'text-gray-400' }}">
            <span class="font-mono">{{ $o->domain }}</span> —
            @if ($o->status === 'registered') registered, renews {{ $o->expires_at?->toFormattedDateString() }}
            @elseif ($o->status === 'failed') payment taken but registration failed ({{ $o->error }}). We'll sort it — contact support.
            @else {{ $o->status }} @endif
        </p>
    @endforeach
</div>
