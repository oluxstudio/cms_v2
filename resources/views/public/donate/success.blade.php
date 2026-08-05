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
        <div class="w-16 h-16 rounded-full bg-pink-100 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold tracking-tight">Thank you! 💜</h1>
        <p class="text-sm text-gray-500 mt-2">
            @if($donation)
                Your donation of <strong>{{ $donation->formattedAmount() }}</strong> means a lot to us.
            @else
                Your generous donation has been received.
            @endif
        </p>
        <a href="{{ url($site->name.'/donate') }}" class="inline-block mt-6 px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white text-sm font-semibold rounded-xl transition-colors">Done</a>
    </div>
</body>
</html>
