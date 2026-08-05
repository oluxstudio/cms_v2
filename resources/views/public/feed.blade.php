<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">

    <header class="bg-white border-b border-gray-100">
        <div class="max-w-2xl mx-auto px-6 py-5">
            <h1 class="text-lg font-bold tracking-tight">{{ ucwords(str_replace('-', ' ', $site->name)) }} <span class="text-gray-400 font-normal">Live Feed</span></h1>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-6 py-10">
        @if($handle === '')
            <div class="text-center py-20">
                <span class="text-5xl">𝕏</span>
                <p class="text-gray-500 mt-4">No X / Twitter handle configured yet.</p>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                {{-- Official X embedded timeline widget — no API key required --}}
                <a class="twitter-timeline"
                   data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}"
                   data-tweet-limit="{{ max(1, min(20, $count)) }}"
                   data-chrome="noheader nofooter transparent"
                   href="https://twitter.com/{{ $handle }}">
                    Posts by &#64;{{ $handle }}
                </a>
                <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
            </div>
            <p class="text-center text-xs text-gray-400 mt-4">
                Live posts from <a href="https://twitter.com/{{ $handle }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">&#64;{{ $handle }}</a>
            </p>
        @endif
    </main>
</body>
</html>
