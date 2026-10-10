<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $fromName }} — an introduction to {{ $toName }}</title>
    {{-- Follow the visitor's light/dark preference (the admin bundle themes by class). --}}
    <script>if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 dark:bg-[#12131b] text-gray-900 dark:text-gray-100 antialiased flex items-start justify-center px-4 py-8 sm:py-14">
<main class="w-full max-w-lg">
    {{-- Brand (the referrer — they're the one asking) --}}
    <div class="flex flex-col items-center text-center mb-6">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $fromName }}" class="h-12 max-w-[200px] object-contain mb-3 dark:bg-white/90 dark:rounded-lg dark:px-2 dark:py-1">
        @else
            <span class="w-14 h-14 rounded-2xl grid place-items-center text-xl font-extrabold bg-gray-900 text-white dark:bg-white dark:text-gray-900 mb-3">{{ mb_strtoupper(mb_substr($fromName, 0, 1)) }}</span>
        @endif
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $fromName }}</p>
    </div>

    <div class="rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-6 sm:p-8">
    @if (session('network.error'))
        <p class="mb-5 rounded-xl px-4 py-3 text-sm bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ session('network.error') }}</p>
    @endif

    @if ($state === 'ask')
        <div class="text-center">
            <h1 class="text-2xl font-extrabold tracking-tight">May we introduce you?</h1>
            <p class="mt-2 text-[15px] text-gray-500 dark:text-gray-400">
                Hi {{ $referral->customer_name }} — {{ $fromName }} thinks <strong class="text-gray-800 dark:text-gray-100">{{ $toName }}</strong> can help with what you need.
            </p>
        </div>

        {{-- Who they'd be introduced to --}}
        <div class="mt-6 rounded-2xl bg-gray-50 dark:bg-white/[0.04] px-5 py-4">
            <p class="font-bold">{{ $toName }}</p>
            @if ($toArea)<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $toArea }}</p>@endif
            @if ($toPitch)<p class="mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $toPitch }}</p>@endif
        </div>

        {{-- What would be shared --}}
        <div class="mt-5">
            <p class="text-sm font-semibold mb-2">If you say yes, {{ $fromName }} will share:</p>
            <ul class="space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($shared as $item)
                    <li class="flex items-start gap-2"><span class="text-emerald-500 mt-0.5">✓</span><span>{{ $item }}</span></li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-gray-400">Nothing else is shared. If you say no, or don't answer, {{ $toName }} never sees your details.</p>
        </div>

        <form method="POST" action="{{ route('network.consent.store', request()->route('token')) }}" class="mt-6 space-y-3">
            @csrf
            <p class="text-xs text-gray-500 dark:text-gray-400 rounded-xl border border-gray-100 dark:border-white/[0.08] px-3.5 py-2.5">{{ $referral->consent_text }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="submit" name="answer" value="yes"
                        class="w-full py-3 rounded-xl text-[15px] font-bold bg-gray-900 text-white dark:bg-white dark:text-gray-900">
                    Yes, introduce me
                </button>
                <button type="submit" name="answer" value="no"
                        class="w-full py-3 rounded-xl text-[15px] font-bold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06]">
                    No thanks
                </button>
            </div>
            @error('answer')<p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
        </form>
    @elseif ($state === 'shared')
        {{-- ── Thank-you state ── --}}
        <div class="text-center py-4">
            <div class="mx-auto w-16 h-16 rounded-full grid place-items-center bg-emerald-100 dark:bg-emerald-500/15 text-3xl mb-4">🤝</div>
            <h1 class="text-2xl font-extrabold tracking-tight">Thank you, {{ $referral->customer_name }}!</h1>
            <p class="mt-2 text-[15px] text-gray-500 dark:text-gray-400">Your details are with {{ $toName }} — they'll be in touch soon.</p>
        </div>
    @elseif ($state === 'refused')
        <div class="text-center py-4">
            <div class="mx-auto w-16 h-16 rounded-full grid place-items-center bg-gray-100 dark:bg-white/[0.06] text-3xl mb-4">👍</div>
            <h1 class="text-2xl font-extrabold tracking-tight">No problem</h1>
            <p class="mt-2 text-[15px] text-gray-500 dark:text-gray-400">We haven't shared your details with {{ $toName }}. You don't need to do anything else.</p>
        </div>
    @else
        <div class="text-center py-4">
            <div class="mx-auto w-16 h-16 rounded-full grid place-items-center bg-gray-100 dark:bg-white/[0.06] text-3xl mb-4">⌛</div>
            <h1 class="text-2xl font-extrabold tracking-tight">This link is no longer active</h1>
            <p class="mt-2 text-[15px] text-gray-500 dark:text-gray-400">Nothing was shared. If you'd still like an introduction to {{ $toName }}, just ask {{ $fromName }}.</p>
        </div>
    @endif
    </div>
    <p class="text-center text-xs text-gray-400 mt-6">Sent by {{ $fromName }} via the Olux Referral Network.</p>
</main>
</body>
</html>
