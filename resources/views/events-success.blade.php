@php $siteLabel = \App\Modules\Events\Mail\TicketConfirmation::siteLabel($site); @endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $order->status === 'paid' ? 'You’re booked' : 'Thanks' }} — {{ $event->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-8 text-center">
        <div class="w-16 h-16 rounded-full {{ $order->status === 'paid' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600' }} grid place-items-center mx-auto mb-5 text-3xl">
            {{ $order->status === 'paid' ? '🎟' : '⏳' }}
        </div>
        @if ($order->status === 'paid')
            <h1 class="text-2xl font-extrabold tracking-tight">You’re booked!</h1>
            <p class="text-sm text-gray-500 mt-2">{{ $order->quantity }} {{ Str::plural('ticket', $order->quantity) }} for <strong>{{ $event->title }}</strong> — we’ve emailed them to {{ $order->buyer_email }}.</p>
            <p class="text-xs text-gray-400 mt-1">{{ $event->whenLabel() }}</p>
            <div class="mt-5 space-y-2 text-left">
                @foreach ($tickets as $t)
                    <a href="{{ $t->url($site->name) }}" class="flex items-center justify-between gap-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 hover:bg-gray-50">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold truncate">{{ $t->attendee_name }}</span>
                            <span class="block text-xs text-gray-500">{{ $t->ticketType?->name ?? 'Ticket' }}</span>
                        </span>
                        <span class="font-mono font-extrabold tracking-[.14em]">{{ $t->code }}</span>
                    </a>
                @endforeach
            </div>
        @elseif ($order->status === 'pending')
            <h1 class="text-2xl font-extrabold tracking-tight">Payment processing</h1>
            <p class="text-sm text-gray-500 mt-2">Thanks — we’re confirming your payment for <strong>{{ $event->title }}</strong>. Your tickets will arrive by email at {{ $order->buyer_email }} in a moment.</p>
        @else
            <h1 class="text-2xl font-extrabold tracking-tight">This order wasn’t completed</h1>
            <p class="text-sm text-gray-500 mt-2">The checkout for <strong>{{ $event->title }}</strong> expired or was cancelled — no payment was taken.</p>
        @endif
        <p class="text-xs text-gray-400 mt-6">Order {{ $order->reference }} · {{ $siteLabel }}</p>
    </div>
</body>
</html>
