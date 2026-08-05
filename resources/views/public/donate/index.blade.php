<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-8"
         x-data="{ amount: '{{ $suggested[0] ?? 10 }}' }">

        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-pink-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">{{ $headline }}</h1>
            <p class="text-sm text-gray-400 mt-1">Every contribution helps. Thank you 💜</p>
        </div>

        @if(session('donate_error'))
        <div class="mb-5 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ session('donate_error') }}</div>
        @endif

        <form method="POST" action="{{ url($site->name.'/donate/checkout') }}" class="space-y-4">
            @csrf

            {{-- Suggested amounts --}}
            <div class="grid grid-cols-4 gap-2">
                @foreach($suggested as $amt)
                <button type="button" @click="amount = '{{ $amt }}'"
                        class="py-2.5 rounded-xl text-sm font-bold border-2 transition-colors"
                        :class="amount === '{{ $amt }}' ? 'border-pink-500 bg-pink-50 text-pink-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                    @php $cpos = config('currencies.'.strtolower($currency).'.position', 'before'); $csym = \App\Support\Money::symbol($currency); @endphp{{ $cpos === 'before' ? $csym.$amt : $amt.' '.$csym }}
                </button>
                @endforeach
            </div>

            {{-- Custom amount --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Amount ({{ \App\Support\Money::symbol($currency) }})</label>
                <input type="number" name="amount" x-model="amount" step="0.01" min="1" required
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-pink-500">
            </div>

            <input type="text" name="name" placeholder="Your name (optional)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-pink-500">
            <input type="email" name="email" placeholder="Email for your receipt (optional)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-pink-500">
            <textarea name="message" rows="2" placeholder="Leave a message (optional)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-pink-500 resize-none"></textarea>

            <button type="submit" class="w-full py-3.5 bg-pink-600 hover:bg-pink-700 text-white font-semibold rounded-xl transition-colors shadow-sm">
                @php $cBefore = config('currencies.'.strtolower($currency).'.position', 'before') === 'before'; $cSym = \App\Support\Money::symbol($currency); @endphp
                Donate <span x-text="amount ? ({{ $cBefore ? 'true' : 'false' }} ? '· {{ $cSym }}' + amount : '· ' + amount + ' {{ $cSym }}') : ''"></span>
            </button>
            <p class="text-xs text-gray-400 text-center">Secure payment via Stripe.</p>
        </form>
    </div>
</body>
</html>
