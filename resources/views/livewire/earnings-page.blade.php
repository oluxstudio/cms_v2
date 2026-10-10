@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $cur = $summary['currency'] ?: 'gbp';
    $money = fn (int $c, ?string $c2 = null) => \App\Support\Money::format($c, $c2 ?: $cur);
    $pct = rtrim(rtrim(number_format($cutPct, 2), '0'), '.');
    $statusMeta = [
        'pending' => ['Pending', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'Waiting for the partner\'s Olux bill to be paid'],
        'ready' => ['Ready', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'Collected — paying out soon'],
        'transferred' => ['Paid', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'Sent to your bank via Stripe'],
        'credited' => ['Credited', 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300', 'Taken off your Olux bill'],
        'failed' => ['Failed', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'We\'ll retry automatically'],
    ];
    $pill = fn (string $st) => $statusMeta[$st] ?? [ucfirst($st), 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300', ''];
    $filters = [
        'all' => ['All', $counts->sum()],
        'pending' => ['Pending', $counts['pending'] ?? 0],
        'ready' => ['Ready', $counts['ready'] ?? 0],
        'paid' => ['Paid', ($counts['transferred'] ?? 0) + ($counts['credited'] ?? 0)],
        'failed' => ['Failed', $counts['failed'] ?? 0],
    ];
    $method = $summary['method'] ?? 'connect_transfer';
    $connectReady = (bool) ($summary['connect_ready'] ?? false);
@endphp
<x-tri-layout title="Earnings" subtitle="Money you've earned by referring customers to partners in the network." :site-name="$site->name"
    :labels="['📊 Overview', '💷 Earnings', '⚡ Quick access']">

    {{-- ── LEFT rail ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$money((int) $summary['lifetime_cents'])" label="Lifetime earnings" sub="after Olux's {{ $pct }}%"
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$money((int) $summary['pending_cents'])" label="Pending" sub="partner not billed yet"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="sky" :value="$money((int) $summary['ready_cents'])" label="Ready to pay" sub="collected"
                icon="M5 13l4 4L19 7" />
        <x-tile accent="lime" :value="$money((int) $summary['paid_cents'])" label="Paid to bank" sub="Stripe transfers"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        <x-tile accent="lavender" :value="$money((int) $summary['credited_cents'])" label="Olux credit" sub="off your bill"
                icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
    </div>
    </x-slot:rail>

    {{-- ── CENTRE ── --}}
    <div class="space-y-5">
        @if ($notice)
            <div class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 text-sm font-semibold">
                <span>{{ $notice }}</span>
                <button type="button" wire:click="$set('notice', '')" class="text-xs opacity-70 hover:opacity-100" aria-label="Dismiss">✕</button>
            </div>
        @endif
        @if ($error)
            <div class="rounded-2xl px-4 py-3 bg-rose-50 dark:bg-rose-500/10 text-rose-800 dark:text-rose-200 text-sm font-semibold" role="alert">{{ $error }}</div>
        @endif

        {{-- Payout method --}}
        <div class="{{ $panel }} p-5 space-y-3">
            <div>
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">How you get paid</p>
                <p class="text-xs text-gray-400">Each payout is the partner's fee minus Olux's {{ $pct }}%.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ([
                    'connect_transfer' => ['🏦', 'Pay to my bank', 'Transferred through your Stripe account once the partner has paid.'],
                    'olux_credit' => ['🧾', 'Olux credit', 'Taken off your next Olux bill instead — no Stripe setup needed.'],
                ] as $key => [$ico, $title, $desc])
                    @php $on = $method === $key; @endphp
                    <button type="button" @if ($canManage) wire:click="setMethod('{{ $key }}')" @else disabled @endif
                            class="text-left rounded-2xl px-4 py-3 border transition-colors bg-white dark:bg-[#1d1e2a]
                                   {{ $on ? 'border-indigo-300 dark:border-indigo-500/40 ring-2 ring-indigo-200 dark:ring-indigo-500/20' : 'border-gray-200 dark:border-white/[0.08] hover:bg-gray-50 dark:hover:bg-white/[0.04]' }}
                                   disabled:cursor-default" aria-pressed="{{ $on ? 'true' : 'false' }}">
                        <span class="flex items-center gap-2">
                            <span class="text-lg">{{ $ico }}</span>
                            <span class="text-[13.5px] font-bold text-gray-900 dark:text-white">{{ $title }}</span>
                            @if ($on)<span class="ml-auto px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Selected</span>@endif
                        </span>
                        <span class="block mt-1 text-[12px] text-gray-500 dark:text-gray-400">{{ $desc }}</span>
                    </button>
                @endforeach
            </div>
            @if ($method === 'connect_transfer' && ! $connectReady)
                <div class="flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3 bg-amber-50 dark:bg-amber-500/10" data-connect-setup>
                    <p class="flex-1 min-w-[12rem] text-[12.5px] text-amber-900 dark:text-amber-100">Finish setting up Stripe payments to receive bank transfers. Until then your earnings wait safely as “Ready”.</p>
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Finish Stripe setup</a>
                </div>
            @endif
            @unless ($canManage)
                <p class="text-[11.5px] text-gray-400">Ask an owner to change how earnings are paid.</p>
            @endunless
        </div>

        {{-- Toolbar --}}
        <div class="{{ $panel }} !rounded-2xl p-3 flex flex-wrap items-center gap-2">
            <div class="flex gap-2 overflow-x-auto no-scrollbar flex-1">
                @foreach ($filters as $key => [$lbl, $n])
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                        class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                            {{ $filter === $key
                                ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                        {{ $lbl }} <span class="opacity-60">{{ $n }}</span>
                    </button>
                @endforeach
            </div>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
        </div>

        @if ($payouts->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-emerald-50 dark:bg-emerald-500/10 text-2xl">💷</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $filter === 'all' ? 'No earnings yet' : 'Nothing here' }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">When a customer you refer becomes a partner's customer, your share shows up here.</p>
                <a href="{{ route('site.network', ['siteID' => $site->name, 'tab' => 'partners']) }}" wire:navigate class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Find partners</a>
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($payouts as $p)
                    @php [$stLabel, $stClass, $stHint] = $pill($p->status); $r = $p->referral; @endphp
                    <div class="{{ $panel }} !rounded-2xl p-5" wire:key="po-{{ $p->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $stClass }}" title="{{ $stHint }}">{{ $stLabel }}</span>
                            <span class="text-[11px] font-mono text-gray-400">{{ $r?->reference ?? '—' }}</span>
                        </div>
                        <p class="mt-2 text-[22px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $money($p->net_cents, $p->currency) }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $r?->customer_name ?? 'Customer' }} → {{ \App\Livewire\NetworkPage::siteLabel($r?->toSite) }}</p>
                        <dl class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05] grid grid-cols-3 gap-2 text-[11.5px]">
                            <div><dt class="text-gray-400">Fee</dt><dd class="font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $money($p->gross_cents, $p->currency) }}</dd></div>
                            <div><dt class="text-gray-400">Olux {{ $pct }}%</dt><dd class="font-semibold text-gray-800 dark:text-gray-100 tabular-nums">−{{ $money($p->olux_cents, $p->currency) }}</dd></div>
                            <div><dt class="text-gray-400">Date</dt><dd class="font-semibold text-gray-800 dark:text-gray-100">{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</dd></div>
                        </dl>
                        @if ($p->status === 'failed' && $p->failure)<p class="mt-2 text-[11px] text-rose-500">{{ $p->failure }}</p>@endif
                    </div>
                @endforeach
            </div>
        @else
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
            <div class="{{ $panel }} !rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">Referral</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 text-right">Fee</th>
                                <th class="px-4 py-3 text-right">Olux {{ $pct }}%</th>
                                <th class="px-4 py-3 text-right">Net</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($payouts as $p)
                                @php [$stLabel, $stClass, $stHint] = $pill($p->status); $r = $p->referral; @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="por-{{ $p->id }}">
                                    <td class="{{ $pad }} font-mono text-[12px] text-gray-500 whitespace-nowrap">{{ $r?->reference ?? '—' }}</td>
                                    <td class="{{ $pad }}">
                                        <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[12rem]">{{ $r?->customer_name ?? '—' }}</span>
                                        @unless ($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[12rem]">→ {{ \App\Livewire\NetworkPage::siteLabel($r?->toSite) }}</span>@endunless
                                    </td>
                                    <td class="{{ $pad }} text-right tabular-nums whitespace-nowrap">{{ $money($p->gross_cents, $p->currency) }}</td>
                                    <td class="{{ $pad }} text-right tabular-nums whitespace-nowrap text-gray-400">−{{ $money($p->olux_cents, $p->currency) }}</td>
                                    <td class="{{ $pad }} text-right tabular-nums whitespace-nowrap font-bold text-gray-900 dark:text-white">{{ $money($p->net_cents, $p->currency) }}</td>
                                    <td class="{{ $pad }} whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $stClass }}" title="{{ $stHint }}">{{ $stLabel }}</span></td>
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($hasMore)
            <div class="text-center">
                <button type="button" wire:click="loadMore" class="{{ $btnSolid }} text-sm px-5 py-2">Show more</button>
            </div>
        @endif
    </div>

    {{-- ── RIGHT rail ── --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Summary</p>
            <dl class="space-y-1.5 text-[12.5px]">
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">On its way</dt><dd class="font-bold text-gray-900 dark:text-white tabular-nums">{{ $money((int) $summary['pending_cents'] + (int) $summary['ready_cents']) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Paid out</dt><dd class="font-bold text-gray-900 dark:text-white tabular-nums">{{ $money((int) $summary['paid_cents'] + (int) $summary['credited_cents']) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Payout method</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $method === 'olux_credit' ? 'Olux credit' : 'Bank (Stripe)' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Stripe</dt><dd class="font-semibold {{ $connectReady ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $connectReady ? 'Ready' : 'Not set up' }}</dd></div>
            </dl>
            <p class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.06] text-[11.5px] text-gray-500 dark:text-gray-400">
                Fees are collected on the partner's next Olux bill. Once it's paid, your share is released.
            </p>
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Network', 'Refer & track', route('site.network', $site->name)],
                    ['Payments', 'Stripe setup', route('site.payments', $site->name)],
                ] as [$lbl, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $lbl }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
