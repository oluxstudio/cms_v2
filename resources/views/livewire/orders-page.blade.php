@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $statusStyles = [
        'pending'   => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        'paid'      => 'bg-blue-100 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        'shipped'   => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'delivered' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'fulfilled' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'return_requested' => 'bg-orange-100 text-orange-700 dark:bg-orange-400/10 dark:text-orange-400',
        'returned'  => 'bg-violet-100 text-violet-700 dark:bg-violet-400/10 dark:text-violet-400',
        'refunded'  => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400',
        'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
    ];
    $statusColor = [
        'pending' => '#f59e0b', 'paid' => '#3b82f6', 'shipped' => '#6366f1', 'delivered' => '#10b981', 'fulfilled' => '#10b981',
        'return_requested' => '#f97316', 'returned' => '#8b5cf6', 'refunded' => '#f43f5e', 'cancelled' => '#94a3b8',
    ];
    $statuses = \App\Models\Order::STATUSES;
    $counts   = $this->statusCounts;
    $ins      = $this->insights;
    $orders   = $this->orders;
    $accent   = 'var(--primary)';
    $delta = fn (?int $d) => $d === null ? null : (($d >= 0 ? '▲ ' : '▼ ').abs($d).'% vs last month');
    $donutColors = [$accent, '#f59e0b', '#10b981', '#ec4899', '#38bdf8'];
    $filters = [
        'all' => ['All', $counts['all'] ?? 0],
        'unfulfilled' => ['Unfulfilled', $counts['group:unfulfilled'] ?? 0],
        'paid' => ['Paid', $counts['group:paid'] ?? 0],
        'fulfilled' => ['Fulfilled', $counts['group:fulfilled'] ?? 0],
        'refunded' => ['Refunded', $counts['group:refunded'] ?? 0],
        'cancelled' => ['Cancelled', $counts['group:cancelled'] ?? 0],
    ];
    $toFulfil = (int) ($counts['paid'] ?? 0);
    $newOrUnfulfilled = (int) ($counts['group:unfulfilled'] ?? 0);
    $returns = (int) ($counts['return_requested'] ?? 0);
    // Payment side of an order: [label, classes].
    $payment = fn ($o) => match ($o->status) {
        'pending' => ['Awaiting payment', 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400'],
        'refunded' => ['Refunded', 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400'],
        'cancelled' => ['Cancelled', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
        default => ['Paid', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'],
    };
    // Fulfilment side of an order: [label, classes].
    $fulfil = function ($o) use ($statusStyles) {
        $collect = $o->fulfilment === 'collection';
        return match ($o->status) {
            'pending' => ['Not started', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
            'paid' => ['Unfulfilled', 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400'],
            'shipped' => [$collect ? 'Ready to collect' : 'Shipped', $statusStyles['shipped']],
            'delivered', 'fulfilled' => [$collect ? 'Collected' : 'Delivered', $statusStyles['delivered']],
            'return_requested' => ['Return requested', $statusStyles['return_requested']],
            'returned' => ['Returned', $statusStyles['returned']],
            default => ['Closed', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
        };
    };
    $attention = $toFulfil || $returns || $ins['stalePending'] || ! $site->stripeReady();
@endphp

<x-tri-layout title="Orders" :site-name="$site->name"
    :subtitle="($counts['all'] ?? 0).' '.Str::plural('order', $counts['all'] ?? 0).' · '.$ins['revenueAll'].' taken all time'"
    :labels="['📊 Overview', '📦 Orders', '⚡ Summary']">

    <x-slot:header>
        <button type="button" wire:click="openQuickInvoice" class="{{ $btnSolid }} text-xs px-3.5 py-2">＋ Create invoice</button>
    </x-slot:header>

    {{-- ── LEFT rail: money & fulfilment at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="{{ $newOrUnfulfilled ? 'rose' : 'ink' }}" wide :value="$newOrUnfulfilled" label="New or unfulfilled"
                :sub="$toFulfil ? $toFulfil.' paid, waiting to ship' : ($ins['awaiting'] ? $ins['awaiting'].' awaiting payment' : 'all orders handled')"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                wire:click="setStatusFilter('unfulfilled')" class="cursor-pointer" />
        <x-tile accent="lime" :value="$ins['monthRevenue']" label="Revenue this month" :sub="$delta($ins['revDelta']) ?? 'paid orders'"
                icon="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$ins['aov']" label="Average order" sub="per paid order · all time"
                icon="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        <x-tile accent="sky" :value="$ins['monthOrders']" label="Orders this month" :sub="$delta($ins['ordDelta']) ?? $ins['customers'].' '.Str::plural('customer', $ins['customers'])"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        <x-tile accent="{{ $ins['refunds'] ? 'rose' : 'lavender' }}" :value="$ins['refunds']" label="Refunds" :sub="$ins['refunds'] ? $ins['refundedTotal'].' returned' : 'none so far'"
                icon="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
                wire:click="setStatusFilter('refunded')" class="cursor-pointer" />
        <x-tile accent="lavender" :value="$ins['repeatCustomers']" label="Repeat customers" :sub="'of '.$ins['customersAll'].' '.Str::plural('buyer', $ins['customersAll'])"
                icon="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @unless($site->stripeReady())
    <div class="px-4 py-3 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        Payments aren't connected — new orders can't be paid by card. Connect Stripe on <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="font-semibold underline">Payments</a>.
    </div>
    @endunless

    {{-- ── Toolbar: search · sort · layout · status pills ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search by order number, customer or item…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="amount">Highest total</option>
                <option value="amount_asc">Lowest total</option>
                <option value="customer">Customer A–Z</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($filters as $key => [$label, $n])
                <button type="button" wire:click="setStatusFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $statusFilter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $n }}</span>
                </button>
            @endforeach
            @unless(array_key_exists($statusFilter, $filters))
                <button type="button" wire:click="setStatusFilter('all')"
                        class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white capitalize">
                    {{ str_replace('_', ' ', $statusFilter) }} <span class="opacity-60">{{ $counts[$statusFilter] ?? 0 }}</span> ✕
                </button>
            @endunless
        </div>
    </div>

    @if($orders->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            @if(($counts['all'] ?? 0) === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No orders yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">When someone buys from your storefront, the order lands here — ready to ship, refund or invoice.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('site.store', $site->name) }}" wire:navigate class="inline-flex text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Manage products</a>
                    <a href="{{ url($site->name.'/store') }}" target="_blank" class="{{ $btnSolid }} text-sm px-4 py-2.5">View storefront ↗</a>
                </div>
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or status.</p>
                <button type="button" x-on:click="$wire.set('search', ''); $wire.setStatusFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all orders</button>
            @endif
        </div>
    @elseif($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($orders as $order)
                @php [$payLabel, $payClass] = $payment($order); [$fulLabel, $fulClass] = $fulfil($order); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl overflow-hidden hover:shadow-md transition-shadow" wire:key="ord-{{ $order->id }}">
                    <button type="button" wire:click="open('{{ $order->id }}')" class="text-left p-5 flex-1 block">
                        <span class="flex items-start gap-3">
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white group-hover:underline">{{ $order->displayNumber() }}</span>
                                <span class="block text-[12px] text-gray-400 mt-0.5">{{ $order->created_at->format('j M Y · H:i') }} · {{ $order->fulfilment === 'collection' ? '🏪 Collection' : '🚚 Delivery' }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-xl font-extrabold tabular-nums leading-none text-gray-900 dark:text-white">{{ $order->formattedTotal() }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}@if((int) $order->units > $order->items_count) · {{ (int) $order->units }} units @endif</span>
                            </span>
                        </span>
                        <span class="mt-3 flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-full grid place-items-center shrink-0 text-[12px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ Str::upper(Str::substr($order->customer_name ?: ($order->customer_email ?: 'G'), 0, 1)) }}</span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $order->customer_name ?: 'Guest' }}</span>
                                <span class="block text-[11px] text-gray-400 truncate">{{ $order->customer_email ?: 'No email' }}</span>
                            </span>
                        </span>
                        <span class="mt-3 flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $payClass }}">💳 {{ $payLabel }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $fulClass }}">📦 {{ $fulLabel }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">{{ $order->stripe_session_id ? 'Card · Stripe' : 'Manual' }}</span>
                        </span>
                    </button>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        @if($order->status === 'paid')
                            <button wire:click="markShipped('{{ $order->id }}')" data-confirm="Mark {{ $order->displayNumber() }} as shipped?" class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">{{ $order->fulfilment === 'collection' ? 'Ready' : 'Ship' }}</button>
                        @elseif($order->status === 'shipped')
                            <button wire:click="markDelivered('{{ $order->id }}')" data-confirm="Mark {{ $order->displayNumber() }} as delivered?" class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Delivered</button>
                        @endif
                        <button wire:click="open('{{ $order->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Open</button>
                        <select wire:change="setStatus('{{ $order->id }}', $event.target.value)" title="Change status"
                                class="ml-auto text-[11px] font-bold capitalize rounded-lg border-0 px-2 py-1 cursor-pointer {{ $statusStyles[$order->status] ?? '' }}">
                            @foreach($statuses as $st)
                                <option value="{{ $st }}" @selected($order->status === $st)>{{ str_replace('_', ' ', $st) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width:640px">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            @unless($compact)<th class="px-4 py-3 text-right">Items</th>@endunless
                            <th class="px-4 py-3 text-right">Total</th>
                            @unless($compact)<th class="px-4 py-3">Payment</th><th class="px-4 py-3">Fulfilment</th>@endunless
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach($orders as $order)
                            @php [$payLabel, $payClass] = $payment($order); [$fulLabel, $fulClass] = $fulfil($order); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors" wire:key="row-{{ $order->id }}">
                                <td class="{{ $pad }} cursor-pointer whitespace-nowrap" wire:click="open('{{ $order->id }}')">
                                    <span class="block text-[13px] font-bold text-gray-900 dark:text-white hover:underline">{{ $order->displayNumber() }}</span>
                                    @unless($compact)<span class="block text-[11px] text-gray-400">{{ $order->created_at->format('j M Y') }} · {{ $order->fulfilment === 'collection' ? 'Collection' : 'Delivery' }}</span>@endunless
                                </td>
                                <td class="{{ $pad }} cursor-pointer" wire:click="open('{{ $order->id }}')">
                                    <span class="block text-[13px] font-semibold text-gray-900 dark:text-white truncate max-w-[160px]">{{ $order->customer_name ?: 'Guest' }}</span>
                                    @unless($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[160px]">{{ $order->customer_email }}</span>@endunless
                                </td>
                                @unless($compact)<td class="{{ $pad }} text-right tabular-nums text-gray-500 dark:text-gray-300">{{ $order->items_count }}</td>@endunless
                                <td class="{{ $pad }} text-right font-extrabold tabular-nums text-gray-900 dark:text-white whitespace-nowrap">{{ $order->formattedTotal() }}</td>
                                @unless($compact)
                                    <td class="{{ $pad }}"><span class="text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {{ $payClass }}">{{ $payLabel }}</span></td>
                                    <td class="{{ $pad }}"><span class="text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {{ $fulClass }}">{{ $fulLabel }}</span></td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <select wire:change="setStatus('{{ $order->id }}', $event.target.value)"
                                            class="text-[11px] font-bold capitalize rounded-lg border-0 px-2 py-1 cursor-pointer {{ $statusStyles[$order->status] ?? '' }}">
                                        @foreach($statuses as $st)
                                            <option value="{{ $st }}" @selected($order->status === $st)>{{ str_replace('_', ' ', $st) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ════════ Order detail drawer ════════ --}}
    @if($this->selected)
    @php $o = $this->selected; @endphp
    <div class="fixed inset-0 z-50 flex justify-end" wire:key="order-{{ $o->id }}">
        <div class="absolute inset-0 bg-black/40" wire:click="closeDetail"></div>
        <div class="relative w-full max-w-md h-full bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.05] shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white dark:bg-[#1d1e2a] border-b border-gray-100 dark:border-white/[0.05] px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Order {{ $o->displayNumber() }}
                    <span class="ml-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">{{ $o->fulfilment === 'collection' ? '🏪 Collection' : '🚚 Delivery' }}</span>
                </h2>
                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full capitalize {{ $statusStyles[$o->status] ?? '' }}">{{ $o->status }}</span>
                    <span class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $o->formattedTotal() }}</span>
                </div>
                @if($o->vatLabel())
                    <p class="text-[11px] text-gray-400 -mt-3 mb-4 text-right">{{ $o->vatLabel() }}</p>
                @endif
                @if($o->delivery_notes)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-500/[0.08]">📝 {{ $o->delivery_notes }}</p>
                @endif
                <div class="mb-5 text-sm">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $o->customer_name ?: 'Guest' }}</p>
                    <p class="text-gray-400 dark:text-gray-500">{{ $o->customer_email }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Placed {{ $o->created_at->format('M j, Y · g:i A') }}@if($o->paid_at) · paid {{ $o->paid_at->diffForHumans() }}@endif</p>
                </div>

                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Items</p>
                <div class="space-y-2 mb-6">
                    @foreach($o->items as $it)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-200">{{ $it->qty }} × {{ $it->name }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ \App\Support\Money::format($it->lineTotalCents(), $o->currency) }}</span>
                    </div>
                    @endforeach
                </div>

                {{-- Order history stepper --}}
                @php
                    $stepMeta = [
                        'placed'           => ['icon' => '🧾', 'label' => 'Placed',           'dot' => 'bg-amber-400'],
                        'pending'          => ['icon' => '🧾', 'label' => 'Placed',           'dot' => 'bg-amber-400'],
                        'paid'             => ['icon' => '💳', 'label' => 'Paid',             'dot' => 'bg-blue-500'],
                        'shipped'          => ['icon' => '📦', 'label' => $o->fulfilment === 'collection' ? 'Ready to collect' : 'Shipped', 'dot' => 'bg-indigo-500'],
                        'delivered'        => ['icon' => '✅', 'label' => 'Delivered',        'dot' => 'bg-emerald-500'],
                        'courier_invited'  => ['icon' => '🚚', 'label' => 'Courier invited',  'dot' => 'bg-sky-500'],
                        'return_requested' => ['icon' => '↩️', 'label' => 'Return requested', 'dot' => 'bg-orange-500'],
                        'returned'         => ['icon' => '📥', 'label' => 'Returned',         'dot' => 'bg-violet-500'],
                        'refunded'         => ['icon' => '💸', 'label' => 'Refunded',         'dot' => 'bg-rose-500'],
                        'cancelled'        => ['icon' => '❌', 'label' => 'Cancelled',        'dot' => 'bg-rose-500'],
                    ];

                    // Every recorded step, in order — Placed always leads.
                    $steps = [['icon' => '🧾', 'label' => 'Placed', 'at' => $o->created_at, 'dot' => 'bg-amber-400', 'by' => null]];
                    if ($o->events->isNotEmpty()) {
                        foreach ($o->events as $ev) {
                            $m = $stepMeta[$ev->status] ?? ['icon' => '•', 'label' => ucfirst(str_replace('_', ' ', $ev->status)), 'dot' => 'bg-gray-400'];
                            $steps[] = $m + ['at' => $ev->created_at, 'by' => $ev->user?->name];
                        }
                    } else {
                        // Legacy orders without history rows: rebuild from the timestamps.
                        foreach ([['paid', $o->paid_at], ['shipped', $o->shipped_at], ['delivered', $o->delivered_at], ['returned', $o->returned_at], ['refunded', $o->refunded_at]] as [$st, $at]) {
                            if ($at) {
                                $steps[] = $stepMeta[$st] + ['at' => $at, 'by' => null];
                            }
                        }
                        if ($o->status === 'cancelled') {
                            $steps[] = $stepMeta['cancelled'] + ['at' => $o->updated_at, 'by' => null];
                        }
                    }

                    // Dimmed remaining happy path while the order is still en route.
                    if (in_array($o->displayStatus(), ['pending', 'paid', 'shipped'], true)) {
                        $ahead = ['pending' => ['paid', 'shipped', 'delivered'], 'paid' => ['shipped', 'delivered'], 'shipped' => ['delivered']][$o->displayStatus()];
                        foreach ($ahead as $st) {
                            $steps[] = $stepMeta[$st] + ['at' => null, 'by' => null];
                        }
                    }
                @endphp
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Order history</p>
                <ol class="mb-5">
                    @foreach($steps as $i => $step)
                    <li class="relative flex items-start gap-3 pb-4 last:pb-0">
                        @unless($loop->last)
                        <span class="absolute left-[5px] top-4 bottom-0 w-px {{ $step['at'] && ($steps[$i + 1]['at'] ?? null) ? 'bg-gray-300 dark:bg-white/20' : 'bg-gray-100 dark:bg-white/[0.06]' }}"></span>
                        @endunless
                        <span class="relative mt-1 w-[11px] h-[11px] rounded-full shrink-0 {{ $step['at'] ? $step['dot'] : 'bg-gray-200 dark:bg-white/[0.1]' }}"></span>
                        <div class="min-w-0 {{ $step['at'] ? '' : 'opacity-50' }}">
                            <p class="text-xs font-semibold text-gray-900 dark:text-white leading-tight">{{ $step['icon'] }} {{ $step['label'] }}</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                {{ $step['at'] ? $step['at']->format('M j, g:i A') : 'Not yet' }}@if($step['by'] ?? null) · by {{ $step['by'] }}@endif
                            </p>
                        </div>
                    </li>
                    @endforeach
                </ol>
                @if($o->status === 'paid')
                <button wire:click="markShipped('{{ $o->id }}')" data-confirm="Mark this order as shipped?" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors mb-2">📦 Mark as shipped</button>
                @elseif($o->status === 'shipped')
                <button wire:click="markDelivered('{{ $o->id }}')" data-confirm="Mark this order as delivered?" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition-colors mb-2">✅ Mark as delivered</button>
                @elseif($o->status === 'return_requested')
                <button wire:click="markReturned('{{ $o->id }}')" data-confirm="Confirm the items are back? Stock will be topped up." class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-colors mb-2">📥 Mark as returned</button>
                @endif

                @if(in_array($o->status, ['paid', 'shipped', 'delivered'], true))
                <div class="flex gap-2 mb-2">
                    <button wire:click="markReturned('{{ $o->id }}')" data-confirm="Mark this order as returned? The items go back into stock."
                            class="flex-1 py-2.5 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-700 dark:text-gray-200 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">↩️ Returned</button>
                    <button wire:click="refundOrder('{{ $o->id }}')" data-confirm="Refund {{ $o->formattedTotal() }} to the customer? {{ $o->stripe_payment_intent ? 'The money is sent back via Stripe.' : 'No card payment on file — the order is only marked refunded.' }}"
                            class="flex-1 py-2.5 bg-white dark:bg-[#1d1e2a] border border-rose-200 dark:border-rose-500/30 text-sm font-semibold text-rose-600 dark:text-rose-400 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">💸 Refund</button>
                </div>
                @elseif($o->status === 'returned')
                <button wire:click="refundOrder('{{ $o->id }}')" data-confirm="Refund {{ $o->formattedTotal() }} to the customer? {{ $o->stripe_payment_intent ? 'The money is sent back via Stripe.' : 'No card payment on file — the order is only marked refunded.' }}"
                        class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl transition-colors mb-2">💸 Refund {{ $o->formattedTotal() }}</button>
                @endif
                {{-- Courier delivery (delivery orders only) --}}
                @if($o->fulfilment !== 'collection' && in_array($o->status, ['paid', 'shipped'], true))
                <div class="mb-3 p-3 rounded-xl bg-gray-50 dark:bg-white/[0.03]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">🚚 Delivery</p>
                    @if($o->shipping_address)
                        <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-pre-line mb-2">{{ $o->shipping_address }}</p>
                    @else
                        <p class="text-xs text-amber-600 dark:text-amber-400 mb-2">No delivery address on file.</p>
                    @endif
                    @if($o->courier_invited_at)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Invited <b>{{ $o->courier_email }}</b> {{ $o->courier_invited_at->diffForHumans() }}.</p>
                    @endif
                    <div class="flex items-center gap-2">
                        <input type="email" wire:model="courierEmail" placeholder="courier@email.com"
                               class="flex-1 px-2.5 py-1.5 rounded-lg text-xs bg-white dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                        <button wire:click="inviteCourier('{{ $o->id }}')"
                                class="px-3 py-1.5 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold whitespace-nowrap">
                            {{ $o->courier_invited_at ? 'Resend' : 'Invite courier' }}
                        </button>
                    </div>
                </div>
                @endif

                <button wire:click="invoiceOrder('{{ $o->id }}')" data-confirm="Create a draft invoice from this order? You'll be taken to the Invoices page."
                        class="w-full py-2.5 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-700 dark:text-gray-200 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">🧾 Invoice this order</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ════════ Quick Create-Invoice modal ════════ --}}
    @if($qiOpen)
    <div class="fixed inset-0 z-50 grid place-items-center p-6" style="background:rgba(10,10,12,.6); backdrop-filter:blur(4px)" wire:click.self="$set('qiOpen', false)">
        <div class="w-full max-w-md bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">New invoice</h2>
                <button wire:click="$set('qiOpen', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
            </div>
            <form wire:submit="createInvoice" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-field.text label="Customer name" model="qiName" />
                        @error('qiName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-field.text label="Customer email" model="qiEmail" type="email" />
                        @error('qiEmail')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="bkf-label">Items</label>
                    <div class="space-y-2">
                        @foreach($qiItems as $i => $row)
                            <div class="flex gap-2 items-start" wire:key="qi-{{ $i }}">
                                <div class="flex-1 min-w-0"><x-field.text model="qiItems.{{ $i }}.description" placeholder="Description" /></div>
                                <div class="w-14"><x-field.text model="qiItems.{{ $i }}.qty" type="number" min="1" placeholder="Qty" /></div>
                                <div class="w-24"><x-field.text model="qiItems.{{ $i }}.price" type="number" step="0.01" min="0" placeholder="Price" /></div>
                                @if(count($qiItems) > 1)
                                    <button type="button" wire:click="qiRemoveItem({{ $i }})" class="mt-2 text-gray-300 hover:text-rose-500">✕</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @error('qiItems.*.description')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    <button type="button" wire:click="qiAddItem" class="mt-2 text-[11px] font-bold text-indigo-600 dark:text-indigo-300">＋ Add item</button>
                </div>
                <div class="w-40"><x-field.text label="Due date" model="qiDue" type="date" /></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold hover:opacity-90">Create draft invoice</button>
                    <button type="button" wire:click="$set('qiOpen', false)" class="px-4 py-2.5 rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-600 dark:text-gray-300">Cancel</button>
                </div>
                <p class="text-[10px] text-gray-400">Created as a draft on the Invoices page — review, then send with its pay link.</p>
            </form>
        </div>
    </div>
    @endif

    </div>

    {{-- Toast --}}
    <div class="fixed bottom-6 right-6 z-[60]"
         x-data="{ toast:'', toastErr:false }"
         x-init="$watch('$wire.successMessage', v => { if(v){ toast=v; toastErr=false; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
                 $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastErr=true;  setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } })">
        <div x-show="toast" x-cloak x-transition class="flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium text-white"
             :class="toastErr ? 'bg-red-600' : 'bg-gray-900'">
            <span x-text="toast"></span>
        </div>
    </div>

    {{-- ══ RIGHT rail: revenue trend · status mix · needs attention · top customers · related ══ --}}
    <x-slot:quick>
        {{-- Revenue trend (last 14 days) --}}
        @php
            $dMax = max(1, collect($ins['daily'])->max('cents'));
            $dSum = collect($ins['daily'])->sum('cents');
        @endphp
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Revenue · last 14 days</p>
            <p class="font-display text-2xl font-extrabold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ \App\Support\Money::format($dSum, $ins['currency']) }}</p>
            <div class="mt-3 flex items-end gap-1 h-24">
                @foreach($ins['daily'] as $bar)
                    <div class="flex-1 h-full flex flex-col justify-end group relative" title="{{ $bar['label'] }} · {{ \App\Support\Money::format($bar['cents'], $ins['currency']) }}">
                        <div class="w-full rounded-md transition-opacity group-hover:opacity-80"
                             style="height:{{ $bar['cents'] > 0 ? max(8, (int) round($bar['cents'] / $dMax * 100)) : 4 }}%; background:{{ $bar['cents'] > 0 ? 'var(--primary)' : 'rgba(148,163,184,.25)' }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-gray-400">
                <span>{{ $ins['daily'][0]['label'] }}</span><span>Today</span>
            </div>
        </div>

        {{-- Orders by status --}}
        @if(($counts['all'] ?? 0) > 0)
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Orders by status</p>
            <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                @foreach($statuses as $s)
                    @if(($counts[$s] ?? 0) > 0)
                        <span style="width:{{ round($counts[$s] / $counts['all'] * 100, 2) }}%;background:{{ $statusColor[$s] }}" title="{{ str_replace('_', ' ', $s) }} · {{ $counts[$s] }}"></span>
                    @endif
                @endforeach
            </div>
            <div class="mt-3 space-y-1.5">
                @foreach($statuses as $s)
                    @if(($counts[$s] ?? 0) > 0)
                        <button type="button" wire:click="setStatusFilter('{{ $s }}')" class="w-full flex items-center gap-2 text-[12.5px] group">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $statusColor[$s] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300 capitalize group-hover:underline">{{ str_replace('_', ' ', $s) }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white tabular-nums">{{ $counts[$s] }}</span>
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        @if($attention)
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if($toFulfil)
                    <button type="button" wire:click="setStatusFilter('paid')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $toFulfil }} to fulfil</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Paid and waiting to ship or hand over →</p>
                    </button>
                @endif
                @if($returns)
                    <button type="button" wire:click="setStatusFilter('return_requested')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-orange-50 dark:bg-orange-500/10 hover:ring-2 hover:ring-orange-200 dark:hover:ring-orange-500/30">
                        <p class="text-[13px] font-bold text-orange-800 dark:text-orange-200">{{ $returns }} return {{ Str::plural('request', $returns) }}</p>
                        <p class="text-[12px] text-orange-700/80 dark:text-orange-200/70">Confirm the goods are back, then refund →</p>
                    </button>
                @endif
                @if($ins['stalePending'])
                    <button type="button" wire:click="setStatusFilter('pending')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $ins['stalePending'] }} unpaid for over a day</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">Payment failed or checkout abandoned — follow up or cancel →</p>
                    </button>
                @endif
                @unless($site->stripeReady())
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">Payments not connected</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Connect Stripe so buyers can pay →</p>
                    </a>
                @endunless
            </div>
        </div>
        @endif

        {{-- Top customers --}}
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-0.5">Top customers</p>
            <p class="text-[11px] text-gray-400 mb-3">By total spent, all time</p>
            @if($ins['topCustomers'] === [])
                <p class="text-[12.5px] text-gray-400 py-2">No paying customers yet.</p>
            @else
                <div class="space-y-2.5">
                    @foreach($ins['topCustomers'] as $c)
                        <button type="button" x-on:click="$wire.set('search', @js($c['email'])); $wire.setStatusFilter('all')" class="w-full flex items-center gap-2.5 text-left group">
                            <span class="w-8 h-8 rounded-full grid place-items-center shrink-0 text-[12px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ Str::upper(Str::substr($c['name'], 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[12.5px] font-semibold text-gray-800 dark:text-gray-100 truncate group-hover:underline">{{ $c['name'] }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $c['orders'] }} {{ Str::plural('order', $c['orders']) }}{{ $c['orders'] > 1 ? ' · repeat' : '' }}</span>
                            </span>
                            <span class="shrink-0 text-[12.5px] font-bold tabular-nums text-gray-900 dark:text-white">{{ $c['total'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sales by product --}}
        @if(count($ins['categories']))
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-0.5">Sales by product</p>
            <p class="text-[11px] text-gray-400 mb-3">Share of revenue, all time</p>
            <div class="flex items-center gap-4">
                @php $R = 34; $C = 2 * M_PI * $R; $off = 0; @endphp
                <svg viewBox="0 0 90 90" class="w-20 h-20 shrink-0 -rotate-90">
                    @foreach($ins['categories'] as $i => $c)
                        @php $len = $C * $c['share'] / 100; @endphp
                        <circle cx="45" cy="45" r="{{ $R }}" fill="none" style="stroke:{{ $donutColors[$i % 5] }}"
                                stroke-width="13" stroke-dasharray="{{ max(0.1, $len - 1.5) }} {{ $C }}"
                                stroke-dashoffset="{{ -$off }}" stroke-linecap="butt"/>
                        @php $off += $len; @endphp
                    @endforeach
                </svg>
                <div class="min-w-0 flex-1 space-y-1.5">
                    @foreach($ins['categories'] as $i => $c)
                        <div class="flex items-center gap-2 text-[11px]" title="{{ $c['revenue'] }} · {{ $c['units'] }} units">
                            <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background:{{ $donutColors[$i % 5] }}"></span>
                            <span class="truncate text-gray-600 dark:text-gray-300 font-medium">{{ $c['name'] }}</span>
                            <span class="ml-auto shrink-0 font-bold text-gray-800 dark:text-gray-100 tabular-nums">{{ $c['share'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach(array_filter([
                    ['Store', 'Products & stock', route('site.store', $site->name)],
                    ['Payments', $site->stripeReady() ? 'Payouts & Stripe' : 'Connect Stripe', route('site.payments', $site->name)],
                    $site->hasFeature('invoices') ? ['Invoices', 'Drafts & pay links', route('site.invoices', $site->name)] : null,
                    ['Contacts', 'Your buyers', route('site.contacts', $site->name)],
                ]) as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
