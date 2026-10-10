@php
    $siteLabel = \App\Modules\Events\Mail\TicketConfirmation::siteLabel($site);
    $valid = $order->status === 'paid' && $event->status !== 'cancelled';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Ticket {{ $ticket->code }} — {{ $event->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-100 text-gray-900 antialiased flex items-center justify-center p-5">
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($event->imageUrl())
            <img src="{{ $event->imageUrl() }}" alt="" class="w-full h-36 object-cover">
        @endif
        <div class="p-6">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] text-indigo-600">{{ $siteLabel }}</p>
            <h1 class="mt-1 text-xl font-extrabold tracking-tight">{{ $event->title }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ $event->whenLabel() }}</p>
            @if ($event->venueLabel())<p class="mt-1 text-sm text-gray-600">📍 {{ $event->venueLabel() }}</p>@endif
            @if ($event->online_url)<p class="mt-1 text-sm"><a href="{{ $event->online_url }}" class="text-indigo-600 font-semibold break-all" rel="noopener">Join online →</a></p>@endif
        </div>

        <div class="relative border-t-2 border-dashed border-gray-200 px-6 py-6 text-center">
            @if ($valid)
                <div class="mx-auto w-[220px] h-[220px] [&>svg]:w-full [&>svg]:h-full">{!! $ticket->qrSvg() !!}</div>
            @endif
            <p class="mt-3 font-mono text-2xl font-extrabold tracking-[.18em]">{{ $ticket->code }}</p>
            <p class="mt-1 text-sm font-semibold">{{ $ticket->attendee_name }}</p>
            <p class="text-xs text-gray-500">{{ $ticket->ticketType?->name ?? 'Ticket' }} · order {{ $order->reference }}</p>

            @if ($event->status === 'cancelled')
                <p class="mt-4 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold px-3 py-2">This event has been cancelled.</p>
            @elseif ($order->status === 'refunded' || $order->status === 'cancelled')
                <p class="mt-4 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold px-3 py-2">This ticket is no longer valid ({{ $order->status }}).</p>
            @elseif ($order->status === 'pending')
                <p class="mt-4 rounded-xl bg-amber-50 text-amber-700 text-sm font-semibold px-3 py-2">Payment not confirmed yet.</p>
            @elseif ($ticket->checked_in_at)
                <p class="mt-4 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-semibold px-3 py-2">Checked in {{ $ticket->checked_in_at->setTimezone($event->safeTimezone())->format('j M, g:i a') }}</p>
            @else
                <p class="mt-4 text-xs text-gray-500">Show this code or QR at the door.</p>
            @endif
        </div>
    </div>
</body>
</html>
