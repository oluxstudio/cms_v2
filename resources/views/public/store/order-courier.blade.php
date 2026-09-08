<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Delivery — order #{{ $order->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-[#15161f] flex items-start justify-center p-6">
<div class="w-full max-w-lg mt-10">
    <div class="bg-white dark:bg-[#1d1e2a] rounded-3xl shadow-xl border border-gray-100 dark:border-white/[0.06] p-8">
        <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">{{ $site->getAttr('business_name', ucwords(str_replace('-', ' ', $site->name))) }} · Delivery</p>
        <div class="flex items-center justify-between gap-3 mb-5">
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Order {{ $order->displayNumber() }}</h1>
            <span class="text-[11px] font-bold px-3 py-1 rounded-full capitalize
                {{ ['paid' => 'bg-blue-100 text-blue-700', 'shipped' => 'bg-indigo-100 text-indigo-700', 'delivered' => 'bg-emerald-100 text-emerald-700'][$order->displayStatus()] ?? 'bg-gray-100 text-gray-600' }}">
                {{ str_replace('_', ' ', $order->displayStatus()) }}
            </span>
        </div>

        @if(session('courier-ok'))
        <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-medium">{{ session('courier-ok') }}</div>
        @endif

        <div class="mb-5 p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-500/[0.08] text-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-500 mb-1">📍 Deliver to</p>
            <p class="text-gray-800 dark:text-gray-100 font-medium whitespace-pre-line">{{ $order->shipping_address ?: 'No address on file — contact the store.' }}</p>
        </div>

        <div class="mb-6 space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
            <p><b>Customer:</b> {{ $order->customer_name ?: '—' }}@if($order->customer_phone) · <a href="tel:{{ $order->customer_phone }}" class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ $order->customer_phone }}</a>@endif</p>
            <p><b>Package:</b> {{ $order->items->sum('qty') }} item{{ $order->items->sum('qty') === 1 ? '' : 's' }}</p>
            <ul class="pl-4 list-disc text-gray-500 dark:text-gray-400">
                @foreach($order->items as $it)<li>{{ $it->qty }} × {{ $it->name }}</li>@endforeach
            </ul>
        </div>

        @if($order->displayStatus() === 'delivered')
        <div class="px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-semibold text-center">✅ Delivered {{ $order->delivered_at?->format('M j, g:i A') }} — thank you!</div>
        @else
        <div class="space-y-2">
            @if($order->status === 'paid')
            <form method="POST" action="{{ route('public.order.courier.status', [$site->name, $order->courier_token]) }}">
                @csrf
                <input type="hidden" name="status" value="shipped">
                <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition-colors">📦 I've picked it up</button>
            </form>
            @endif
            @if(in_array($order->status, ['paid', 'shipped'], true))
            <form method="POST" action="{{ route('public.order.courier.status', [$site->name, $order->courier_token]) }}"
                  onsubmit="return confirm('Confirm the package was delivered to the address above?')">
                @csrf
                <input type="hidden" name="status" value="delivered">
                <button type="submit" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors">✅ Mark as delivered</button>
            </form>
            @else
            <p class="text-sm text-gray-400 text-center">This order is {{ str_replace('_', ' ', $order->displayStatus()) }} — no delivery action available.</p>
            @endif
        </div>
        @endif
    </div>
</div>
</body>
</html>
