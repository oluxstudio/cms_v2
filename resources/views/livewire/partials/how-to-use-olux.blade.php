{{-- "How to use Olux" guide on the sites page. Expects $panel, $pillOutline, $guide, $inside from the parent view.
     Starts open for new accounts (≤2 sites); the header's "How to use" button
     (event: open-how-to) opens it and scrolls to it. --}}
    {{-- ── How to use Olux ── --}}
    <section id="how-to-use" class="{{ $panel }} p-6 scroll-mt-24"
             x-data="{ open: @js(count($sites) <= 2) }"
             @open-how-to.window="open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
        <button type="button" @click="open = ! open" :aria-expanded="open" aria-controls="how-to-use-body" class="w-full flex items-start justify-between gap-4 text-left">
            <span>
                <span class="block text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">How to use Olux</span>
                <span class="block mt-1 text-xl font-extrabold text-gray-900 dark:text-white">From a new site to your first customer</span>
                <span class="block mt-1 text-[13.5px] text-gray-500 dark:text-gray-400">Six steps. Most people are live in an afternoon; you can stop at any point and come back.</span>
            </span>
            <span class="shrink-0 mt-1 w-9 h-9 rounded-full grid place-items-center border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 transition-transform" :class="open && 'rotate-180'" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div id="how-to-use-body" x-show="open" x-collapse x-cloak>
        <ol class="mt-5 grid sm:grid-cols-2 gap-3">
            @foreach ($guide as $i => [$icon, $gTitle, $gText])
                <li class="flex gap-3 rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-4">
                    <span class="shrink-0 w-9 h-9 rounded-xl grid place-items-center text-lg bg-white dark:bg-[#1d1e2a] shadow-sm" aria-hidden="true">{{ $icon }}</span>
                    <span class="min-w-0">
                        <span class="block text-[14px] font-bold text-gray-900 dark:text-white">{{ $i + 1 }}. {{ $gTitle }}</span>
                        <span class="block mt-0.5 text-[12.5px] leading-relaxed text-gray-600 dark:text-gray-300">{{ $gText }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        <h3 class="mt-6 text-[15px] font-extrabold text-gray-900 dark:text-white">Inside each site</h3>
        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Once you open a site, the menu at the top is grouped like this:</p>
        <ul class="mt-3 grid sm:grid-cols-2 gap-x-6 gap-y-2">
            @foreach ($inside as [$iIcon, $iTitle, $iText])
                <li class="flex items-start gap-2.5">
                    <span class="shrink-0 mt-0.5 w-7 h-7 rounded-lg grid place-items-center" style="background:var(--primary-soft);color:var(--primary)">
                        <x-dynamic-component :component="'icons.'.$iIcon" class="w-3.5 h-3.5" />
                    </span>
                    <span class="text-[13px] text-gray-600 dark:text-gray-300"><b class="text-gray-900 dark:text-white">{{ $iTitle }}</b> — {{ $iText }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('how-it-works') }}" class="inline-flex items-center gap-1.5 min-h-[40px] px-5 rounded-full text-sm font-bold" style="background:var(--foreground);color:var(--background)">Read the full guide →</a>
            <a href="{{ route('tutorial') }}" class="{{ $pillOutline }}">20-minute tutorial</a>
        </div>
        </div>
    </section>
