@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $usd = fn (float $v) => '$'.number_format($v, $v < 10 ? 2 : 0);
    $tok = fn (int $n) => $n >= 1_000_000 ? number_format($n / 1_000_000, 1).'M' : ($n >= 1000 ? number_format($n / 1000, 1).'k' : (string) $n);
@endphp

<x-tri-layout title="AI usage" subtitle="What the AI assistant is used for, what it costs, and the switch that turns it off."
    :labels="['📊 Numbers', '🤖 Usage', 'ℹ️ Provider']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm">
            @foreach (['accounts' => 'By account', 'sites' => 'By site', 'models' => 'By model'] as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>{{ $tl }}</button>
            @endforeach
        </div>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$usd($stats['cost'])" label="Estimated cost · {{ now()->format('F') }}" :sub="$usd($stats['last_cost']).' last month'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="sky" :value="$tok($stats['tokens'])" label="Tokens" sub="this month"
                icon="M4 6h16M4 12h16M4 18h7" />
        <x-tile accent="lavender" :value="number_format($stats['turns'])" label="Assistant turns" sub="this month"
                icon="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        <x-tile :accent="$stats['at_cap'] ? 'rose' : 'lime'" :value="$stats['at_cap']" label="Accounts at their cap" sub="this month"
                icon="M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
    </div>

    {{-- Master switch --}}
    <div class="{{ $panel }} p-5 mt-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[14px] font-bold text-gray-900 dark:text-white">AI assistant</p>
                <p class="text-[12px] text-gray-500 dark:text-gray-400">{{ $enabled ? 'Available to clients' : 'Switched off for everyone' }}</p>
            </div>
            <button wire:click="toggleEnabled" role="switch" aria-checked="{{ $enabled ? 'true' : 'false' }}"
                    @if ($enabled) data-confirm="Turn the AI assistant off for every client?" @endif
                    class="relative w-12 h-7 rounded-full transition-colors {{ $enabled ? '' : 'bg-gray-300 dark:bg-white/20' }}"
                    @if ($enabled) style="background:var(--primary)" @endif>
                <span class="absolute top-1 w-5 h-5 rounded-full bg-white shadow transition-all {{ $enabled ? 'left-6' : 'left-1' }}"></span>
                <span class="sr-only">Toggle the AI assistant</span>
            </button>
        </div>
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto space-y-4">
        {{-- Tokens per day --}}
        <div class="{{ $panel }} p-5">
            <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Tokens per day · 30 days</h2>
            <div class="mt-4 flex items-end gap-[3px] h-28" role="img" aria-label="Tokens per day for the last 30 days">
                @foreach ($days as $d)
                    <span class="flex-1 rounded-t-md transition-all" title="{{ $d['d']->format('j M') }}: {{ number_format($d['t']) }} tokens"
                          style="height: {{ max(3, (int) round($d['t'] / $dayMax * 100)) }}%; background: {{ $d['t'] ? 'var(--primary)' : 'color-mix(in srgb, var(--primary) 18%, transparent)' }}"></span>
                @endforeach
            </div>
            <div class="mt-1.5 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                <span>{{ $days->first()['d']->format('j M') }}</span><span>today</span>
            </div>
        </div>

        @if ($tab === 'accounts')
            <div class="{{ $panel }} overflow-hidden">
                @forelse ($accountRows as $r)
                    <div class="px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.account', $r['user']->id) }}" wire:navigate class="text-[14px] font-bold text-gray-900 dark:text-white hover:underline">{{ $r['user']->name }}</a>
                            <span class="text-[11.5px] text-gray-500">{{ $r['user']->currentSubscription()->badgeLabel() }}</span>
                            <span class="ml-auto text-[13px] font-bold tabular-nums text-gray-900 dark:text-white">{{ $tok($r['tokens']) }}</span>
                            <span class="text-[11.5px] text-gray-500">{{ $r['turns'] }} turns</span>
                        </div>
                        @if ($r['limit'] !== null)
                            <div class="mt-2 flex items-center gap-2">
                                <span class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                                    <span class="block h-full rounded-full" style="width: {{ $r['pct'] }}%; background: {{ $r['pct'] >= 100 ? '#e11d48' : ($r['pct'] >= 80 ? '#f59e0b' : 'var(--primary)') }}"></span>
                                </span>
                                <span class="text-[11.5px] {{ $r['pct'] >= 100 ? 'font-bold text-rose-600' : 'text-gray-500' }}">{{ $r['pct'] }}% of {{ $tok($r['limit']) }}</span>
                            </div>
                        @else
                            <p class="mt-1 text-[11.5px] text-gray-500">No monthly cap on this plan.</p>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No AI use this month.</p>
                @endforelse
            </div>

        @elseif ($tab === 'sites')
            <div class="{{ $panel }} overflow-hidden">
                @forelse ($siteRows as $r)
                    <div class="px-5 py-3 flex items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <span class="flex-1 min-w-0 text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $r->site }}</span>
                        <span class="text-[12px] text-gray-500">{{ $r->turns }} turns</span>
                        <span class="text-[13px] font-bold tabular-nums text-gray-900 dark:text-white">{{ $tok((int) $r->tokens) }}</span>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No AI use this month.</p>
                @endforelse
            </div>

        @else
            <div class="{{ $panel }} overflow-hidden">
                @forelse ($byModel as $m)
                    <div class="px-5 py-3.5 flex flex-wrap items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <span class="flex-1 min-w-0 font-mono text-[13px] font-bold text-gray-900 dark:text-white truncate">{{ $m['model'] }}</span>
                        <span class="text-[12px] text-gray-500">{{ $m['turns'] }} turns · {{ $tok($m['in']) }} in · {{ $tok($m['out']) }} out</span>
                        <span class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $usd($m['cost']) }}</span>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No AI use this month.</p>
                @endforelse
            </div>

            <form wire:submit="savePrices" class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Prices behind the estimates</h2>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mb-3">US dollars per million tokens. Check your provider's current price list.</p>
                <div class="space-y-2">
                    @foreach ($prices as $i => $p)
                        <div class="flex flex-wrap items-start gap-2" wire:key="price-{{ $i }}">
                            <input type="text" wire:model="prices.{{ $i }}.model" placeholder="model name" aria-label="Model" class="bkf-input flex-1 min-w-[12rem] font-mono">
                            <input type="number" step="0.01" min="0" wire:model="prices.{{ $i }}.in" aria-label="Input price" placeholder="in" class="bkf-input w-24">
                            <input type="number" step="0.01" min="0" wire:model="prices.{{ $i }}.out" aria-label="Output price" placeholder="out" class="bkf-input w-24">
                            <button type="button" wire:click="removePrice({{ $i }})" aria-label="Remove" class="{{ $btnOutline }} !px-3">✕</button>
                        </div>
                        @error("prices.$i.model")<p class="text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    @endforeach
                </div>
                <div class="mt-4 flex justify-between gap-2">
                    <button type="button" wire:click="addPrice" class="{{ $btnOutline }}">+ Add model</button>
                    <button type="submit" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Save prices</button>
                </div>
            </form>
        @endif
    </div>

    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Provider</h3>
            @foreach ([
                ['Driver', ucfirst($driver)],
                ['Model', $model ?: '—'],
                ['API key', $configured ? 'Set' : 'Missing'],
                ['Hourly limit', $perHour.' turns per site'],
            ] as [$k, $v])
                <div class="flex items-center justify-between gap-3 py-1.5 text-[12.5px] {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="text-gray-500 dark:text-gray-400">{{ $k }}</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100 truncate {{ $k === 'API key' && ! $configured ? 'text-rose-600' : '' }}">{{ $v }}</span>
                </div>
            @endforeach
            <div class="mt-3 flex items-center gap-2">
                <button wire:click="checkReachable" class="{{ $btnOutline }}">
                    <span wire:loading.remove wire:target="checkReachable">Test connection</span>
                    <span wire:loading wire:target="checkReachable">Testing…</span>
                </button>
                @if ($reachable !== null)
                    <span class="text-[12.5px] font-bold {{ $reachable ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600' }}">{{ $reachable ? 'Reachable' : 'Not reachable' }}</span>
                @endif
            </div>
            <p class="mt-2 text-[11.5px] text-gray-500 dark:text-gray-400">Driver, model and key come from the server's environment settings.</p>
        </div>

        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">Monthly allowance</h3>
                <a href="{{ route('admin.plans') }}" wire:navigate class="text-[12px] font-bold hover:underline" style="color:var(--primary)">Edit on Plans →</a>
            </div>
            @foreach ($planCaps as $p)
                <div class="flex items-center justify-between py-1.5 text-[12.5px] {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="text-gray-700 dark:text-gray-200 font-semibold">{{ $p['name'] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $p['cap'] === null ? 'Unlimited' : $tok((int) $p['cap']).' tokens' }}</span>
                </div>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
