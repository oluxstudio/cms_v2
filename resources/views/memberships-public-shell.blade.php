{{-- Standalone shell for the member-facing membership pages (no admin chrome). --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ $siteTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 dark:bg-[#12131b] text-gray-900 dark:text-gray-100 antialiased flex items-center justify-center p-6">
    <main class="max-w-md w-full bg-white dark:bg-[#1d1e2a] rounded-3xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-8 sm:p-10">
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-gray-400 mb-4">{{ $siteTitle }} · Membership</p>
        @if (session('membership_status'))
            <p class="mb-4 rounded-2xl px-4 py-3 text-sm bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200">{{ session('membership_status') }}</p>
        @endif
        @if (session('membership_error'))
            <p class="mb-4 rounded-2xl px-4 py-3 text-sm bg-rose-50 dark:bg-rose-500/10 text-rose-800 dark:text-rose-200">{{ session('membership_error') }}</p>
        @endif
        @yield('content')
    </main>
    <x-confirm-modal />
</body>
</html>
