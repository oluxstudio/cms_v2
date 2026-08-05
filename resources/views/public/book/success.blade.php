<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking confirmed — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">
    <main class="max-w-lg mx-auto px-6 py-20 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold">Booking confirmed</h1>
        @if($appt)
        <p class="text-gray-500 mt-2">Thanks, {{ $appt->customer_name }} — we've got you down for:</p>
        <div class="mt-6 bg-white rounded-2xl border border-gray-100 p-6 text-left inline-block w-full">
            <p class="text-lg font-bold">{{ optional($appt->service)->name ?? 'Appointment' }}</p>
            <p class="text-indigo-600 font-semibold mt-1">{{ $appt->starts_at->format('l, F j, Y') }} at {{ $appt->starts_at->format('g:i A') }}</p>
            <p class="text-sm text-gray-400 mt-3">A confirmation has been sent to {{ $appt->customer_email }}.</p>
        </div>
        @else
        <p class="text-gray-500 mt-2">Your appointment has been booked.</p>
        @endif
        <div class="mt-8">
            <a href="{{ route('public.book', ['siteName' => $site->name]) }}" class="text-sm font-semibold text-indigo-600 hover:underline">Book another →</a>
        </div>
    </main>
</body>
</html>
