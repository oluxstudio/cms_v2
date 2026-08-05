<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold tracking-tight">Thank you!</h1>
        <p class="text-sm text-gray-500 mt-2">
            @if($order)
                Your order <strong>#{{ $order->id }}</strong> for {{ $order->formattedTotal() }} is confirmed.
            @else
                Your payment was received.
            @endif
            A receipt has been sent to your email.
        </p>
        <a href="{{ url($site->name.'/store') }}" class="inline-block mt-6 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">Continue shopping</a>
    </div>
</body>
</html>
