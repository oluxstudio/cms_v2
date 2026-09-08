<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Order #{{ $order->id }} — {{ $site->getAttr('business_name', ucwords(str_replace('-', ' ', $site->name))) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-[#15161f] flex items-start justify-center p-6">
@php
    $badge = [
        'pending'   => 'bg-amber-100 text-amber-700',
        'paid'      => 'bg-blue-100 text-blue-700',
        'shipped'   => 'bg-indigo-100 text-indigo-700',
        'delivered' => 'bg-emerald-100 text-emerald-700',
        'return_requested' => 'bg-orange-100 text-orange-700',
        'returned'  => 'bg-violet-100 text-violet-700',
        'refunded'  => 'bg-rose-100 text-rose-700',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ][$order->displayStatus()] ?? 'bg-gray-100 text-gray-600';
    $stepMeta = [
        'pending'          => ['icon' => '🧾', 'label' => 'Placed',           'dot' => 'bg-amber-400'],
        'paid'             => ['icon' => '💳', 'label' => 'Paid',             'dot' => 'bg-blue-500'],
        'shipped'          => ['icon' => '📦', 'label' => $order->fulfilment === 'collection' ? 'Ready to collect' : 'On its way', 'dot' => 'bg-indigo-500'],
        'delivered'        => ['icon' => '✅', 'label' => 'Delivered',        'dot' => 'bg-emerald-500'],
        'courier_invited'  => ['icon' => '🚚', 'label' => 'Courier assigned', 'dot' => 'bg-sky-500'],
        'return_requested' => ['icon' => '↩️', 'label' => 'Return requested', 'dot' => 'bg-orange-500'],
        'returned'         => ['icon' => '📥', 'label' => 'Returned',         'dot' => 'bg-violet-500'],
        'refunded'         => ['icon' => '💸', 'label' => 'Refunded',         'dot' => 'bg-rose-500'],
        'cancelled'        => ['icon' => '❌', 'label' => 'Cancelled',        'dot' => 'bg-rose-500'],
    ];
    $steps = [['icon' => '🧾', 'label' => 'Order placed', 'at' => $order->created_at, 'dot' => 'bg-amber-400']];
    if ($order->events->isNotEmpty()) {
        foreach ($order->events as $ev) {
            $m = $stepMeta[$ev->status] ?? ['icon' => '•', 'label' => ucfirst(str_replace('_', ' ', $ev->status)), 'dot' => 'bg-gray-400'];
            $steps[] = $m + ['at' => $ev->created_at];
        }
    } else {
        foreach ([['paid', $order->paid_at], ['shipped', $order->shipped_at], ['delivered', $order->delivered_at], ['returned', $order->returned_at], ['refunded', $order->refunded_at]] as [$st, $at]) {
            if ($at) $steps[] = $stepMeta[$st] + ['at' => $at];
        }
    }
    if (in_array($order->displayStatus(), ['pending', 'paid', 'shipped'], true)) {
        foreach (['pending' => ['paid', 'shipped', 'delivered'], 'paid' => ['shipped', 'delivered'], 'shipped' => ['delivered']][$order->displayStatus()] as $st) {
            $steps[] = $stepMeta[$st] + ['at' => null];
        }
    }
@endphp
<div class="w-full max-w-lg mt-10">
    <div class="bg-white dark:bg-[#1d1e2a] rounded-3xl shadow-xl border border-gray-100 dark:border-white/[0.06] p-8">
        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">{{ $site->getAttr('business_name', ucwords(str_replace('-', ' ', $site->name))) }}</p>
        <div class="flex items-center justify-between gap-3 mb-6">
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Your order</h1>
            <span class="text-[11px] font-bold px-3 py-1 rounded-full capitalize {{ $badge }}">{{ str_replace('_', ' ', $order->displayStatus()) }}</span>
        </div>

        <div class="space-y-2 mb-6">
            @foreach($order->items as $it)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-700 dark:text-gray-200">{{ $it->qty }} × {{ $it->name }}</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ \App\Support\Money::format($it->lineTotalCents(), $order->currency) }}</span>
            </div>
            @endforeach
            <div class="flex items-center justify-between text-sm pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                <span class="font-bold text-gray-900 dark:text-white">Total</span>
                <span class="font-extrabold text-gray-900 dark:text-white">{{ $order->formattedTotal() }}</span>
            </div>
            @if($order->vatLabel())
                <p class="text-[11px] text-gray-400 text-right">{{ $order->vatLabel() }}</p>
            @endif
        </div>

        @if($order->fulfilment === 'collection')
        <div class="mb-6 p-4 rounded-2xl bg-gray-50 dark:bg-white/[0.03] text-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1">🏪 Collection</p>
            <p class="text-gray-700 dark:text-gray-200">We'll let you know when your order is ready to collect.</p>
        </div>
        @elseif($order->shipping_address)
        <div class="mb-6 p-4 rounded-2xl bg-gray-50 dark:bg-white/[0.03] text-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1">Delivering to</p>
            <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $order->shipping_address }}</p>
        </div>
        @endif

        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-3">Progress</p>
        <ol>
            @foreach($steps as $i => $step)
            <li class="relative flex items-start gap-3 pb-4 last:pb-0">
                @unless($loop->last)
                <span class="absolute left-[5px] top-4 bottom-0 w-px {{ $step['at'] && ($steps[$i + 1]['at'] ?? null) ? 'bg-gray-300 dark:bg-white/20' : 'bg-gray-100 dark:bg-white/[0.06]' }}"></span>
                @endunless
                <span class="relative mt-1 w-[11px] h-[11px] rounded-full shrink-0 {{ $step['at'] ? $step['dot'] : 'bg-gray-200 dark:bg-white/[0.1]' }}"></span>
                <div class="{{ $step['at'] ? '' : 'opacity-50' }}">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ $step['icon'] }} {{ $step['label'] }}</p>
                    <p class="text-xs text-gray-400">{{ $step['at'] ? $step['at']->format('M j, Y · g:i A') : 'Not yet' }}</p>
                </div>
            </li>
            @endforeach
        </ol>

        <p class="mt-6 text-xs text-gray-400">Order <span class="font-mono">{{ $order->displayNumber() }}</span>@if($order->customer_email) · {{ $order->customer_email }}@endif</p>
    </div>
</div>
</body>
</html>
