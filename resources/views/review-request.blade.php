<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Review {{ $siteName }}</title>
    {{-- Follow the visitor's light/dark preference (the admin bundle themes by class). --}}
    <script>if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full bg-gray-50 dark:bg-[#12131b] text-gray-900 dark:text-gray-100 antialiased flex items-start justify-center px-4 py-8 sm:py-14">
@php
    $done = $review !== null || session('reviews.thanks');
    $words = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];
    $input = 'w-full px-3.5 py-2.5 rounded-xl text-[15px] bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.1] text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-400/50';
@endphp
<main class="w-full max-w-lg">
    {{-- Brand --}}
    <div class="flex flex-col items-center text-center mb-6">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-12 max-w-[200px] object-contain mb-3 dark:bg-white/90 dark:rounded-lg dark:px-2 dark:py-1">
        @else
            <span class="w-14 h-14 rounded-2xl grid place-items-center text-xl font-extrabold bg-gray-900 text-white dark:bg-white dark:text-gray-900 mb-3">{{ mb_strtoupper(mb_substr($siteName, 0, 1)) }}</span>
        @endif
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $siteName }}</p>
    </div>

    <div class="rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-6 sm:p-8">
    @if ($done)
        {{-- ── Thank-you state ── --}}
        <div class="text-center py-4">
            <div class="mx-auto w-16 h-16 rounded-full grid place-items-center bg-amber-100 dark:bg-amber-500/15 text-3xl mb-4">⭐</div>
            <h1 class="text-2xl font-extrabold tracking-tight">Thank you{{ $req->name ? ', '.$req->name : '' }}!</h1>
            <p class="mt-2 text-[15px] text-gray-500 dark:text-gray-400">Your review has been received. It really helps {{ $siteName }}.</p>
            @if ($review)
                <div class="mt-6 text-left rounded-2xl bg-gray-50 dark:bg-white/[0.04] px-5 py-4">
                    <p class="text-amber-500 text-lg tracking-[3px]" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300 dark:text-gray-600">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                    @if ($review->title)<p class="mt-1 font-bold">{{ $review->title }}</p>@endif
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($review->body, 400) }}</p>
                </div>
            @endif
            @if ($googleUrl && filter_var($googleUrl, FILTER_VALIDATE_URL))
                <a href="{{ $googleUrl }}" target="_blank" rel="noopener"
                   class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06]">
                    Share it on Google too ↗
                </a>
            @endif
        </div>
    @else
        {{-- ── Review form ── --}}
        <form method="POST" action="{{ route('reviews.request.store', $req->token) }}" enctype="multipart/form-data"
              x-data="{ rating: {{ (int) old('rating', 0) }}, hover: 0, words: @js($words), photo: '', sending: false }"
              @submit="sending = true" class="space-y-5">
            @csrf
            <div class="text-center">
                <h1 class="text-2xl font-extrabold tracking-tight">How did we do?</h1>
                <p class="mt-1.5 text-[15px] text-gray-500 dark:text-gray-400">Tap the stars to rate {{ $siteName }}.</p>
            </div>

            {{-- Star picker --}}
            <div class="text-center">
                <div class="inline-flex gap-1" role="radiogroup" aria-label="Your rating" @mouseleave="hover = 0">
                    @for ($s = 1; $s <= 5; $s++)
                        <button type="button" role="radio" :aria-checked="rating === {{ $s }}" aria-label="{{ $s }} {{ $s === 1 ? 'star' : 'stars' }}"
                                @click="rating = {{ $s }}" @mouseenter="hover = {{ $s }}"
                                class="p-1 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 transition-transform active:scale-90">
                            <svg class="w-10 h-10 sm:w-11 sm:h-11 transition-colors" viewBox="0 0 24 24"
                                 :class="(hover || rating) >= {{ $s }} ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700'" fill="currentColor">
                                <path d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.72-2.89a.56.56 0 00-.59 0l-4.72 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.35L11.48 3.5z"/>
                            </svg>
                        </button>
                    @endfor
                </div>
                <p class="h-5 mt-1 text-sm font-semibold text-amber-600 dark:text-amber-400" x-text="words[hover || rating] || ''"></p>
                <input type="hidden" name="rating" :value="rating || ''">
                @error('rating')<p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="rv-name" class="block text-sm font-semibold mb-1.5">Your name</label>
                <input id="rv-name" name="name" type="text" maxlength="120" required value="{{ old('name', $req->name) }}" class="{{ $input }}" autocomplete="name">
                <p class="text-xs text-gray-400 mt-1">Shown with your review. Your email is never shown.</p>
                @error('name')<p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="rv-title" class="block text-sm font-semibold mb-1.5">Headline <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="rv-title" name="title" type="text" maxlength="160" value="{{ old('title') }}" class="{{ $input }}" placeholder="Sum it up in a few words">
                @error('title')<p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="rv-body" class="block text-sm font-semibold mb-1.5">Your review</label>
                <textarea id="rv-body" name="body" rows="5" maxlength="3000" required class="{{ $input }} resize-y" placeholder="What did you like? How was the service?">{{ old('body') }}</textarea>
                @error('body')<p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="block text-sm font-semibold mb-1.5">Add a photo <span class="font-normal text-gray-400">(optional)</span></span>
                <label class="flex items-center gap-3 px-3.5 py-3 rounded-xl border border-dashed border-gray-300 dark:border-white/[0.15] cursor-pointer hover:border-amber-400 bg-white dark:bg-white/[0.03]">
                    <span class="w-9 h-9 rounded-lg grid place-items-center bg-gray-100 dark:bg-white/[0.06] shrink-0">📷</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 truncate" x-text="photo || 'Choose an image (JPG, PNG or WebP, up to 6 MB)'"></span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" @change="photo = $event.target.files[0]?.name || ''">
                </label>
                @error('photo')<p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
            </div>
            {{-- Honeypot: humans never see or fill this --}}
            <div class="hidden" aria-hidden="true"><label>Leave empty <input type="text" name="_hp" tabindex="-1" autocomplete="off"></label></div>

            <button type="submit" :disabled="! rating || sending"
                    class="w-full py-3 rounded-xl text-[15px] font-bold bg-gray-900 text-white dark:bg-white dark:text-gray-900 disabled:opacity-40 disabled:cursor-not-allowed transition-opacity">
                <span x-text="sending ? 'Sending…' : 'Send review'">Send review</span>
            </button>
        </form>
    @endif
    </div>
    <p class="text-center text-xs text-gray-400 mt-6">Your review may be shown on {{ $siteName }}'s website.</p>
</main>
</body>
</html>
