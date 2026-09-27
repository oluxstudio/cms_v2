@php
    $money = fn ($cents, $cur = 'gbp') => ($cur === 'gbp' ? '£' : strtoupper($cur).' ').number_format($cents / 100, $cents % 100 ? 2 : 0);
    $preview = $template->live_preview_url ?: (is_file(public_path('nuxt-preview/'.($template->builtin_key ?: $template->slug).'/index.html'))
        ? url('nuxt-preview/'.($template->builtin_key ?: $template->slug).'/') : null);
@endphp
<x-tri-layout :title="$template->name" :subtitle="'by '.($template->creator?->name ?? 'Olux Studio').($template->category ? ' · '.$template->category : '')"
    :labels="['📊 Facts', '🖼 Template', '🛒 Get it']" quick-width="lg:!w-[350px] xl:!w-[350px] lg:!max-w-[350px]">

    <x-slot:header>
        <a href="{{ route('marketplace', $site->name) }}{{ request()->header('referer') && str_contains(request()->header('referer'), '/marketplace?') ? '?'.parse_url(request()->header('referer'), PHP_URL_QUERY) : '' }}"
           class="fx inline-flex items-center gap-1.5 min-h-[40px] px-3.5 rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to templates
        </a>
    </x-slot:header>

    {{-- ══ LEFT rail: template facts as tiles ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$template->isFree() ? 'Free' : $money($template->price_cents, $template->currency)" label="Price"
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                :sub="$licence" />
        <x-tile accent="lime" :value="number_format($template->installs_count)" label="Installs"
                icon="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" sub="across Olux" />
        <x-tile accent="lavender" :value="$template->rating_count ? number_format($template->rating_avg, 1).'★' : '—'" label="Rating"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"
                :sub="$template->rating_count.' reviews'" />
        <x-tile accent="sky" :value="$template->versions->first()?->version ?? 'v1'" label="Latest version"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                :sub="$template->versions->first()?->created_at?->format('j M Y') ?? 'current'" />
    </div>
    </x-slot:rail>

{{-- ══ CENTER: the template itself ══ --}}
<div class="max-w-[52rem] mx-auto">
    @if ($justAdded)
        <div class="mb-4 px-4 py-3 rounded-2xl border border-emerald-200 dark:border-emerald-500/25 bg-emerald-50/70 dark:bg-emerald-500/[0.07] text-sm">
            <span class="font-bold text-emerald-700 dark:text-emerald-400">Added to your library.</span>
            <span class="text-gray-600 dark:text-gray-300">Use it on any site from that site's Design page.</span>
        </div>
    @endif

    {{-- large preview --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden mb-4">
        <div class="aspect-[16/10] bg-gray-100 dark:bg-white/[0.05]">
            @if ($template->thumbnail_url)
                <img src="{{ $template->thumbnail_url }}" alt="{{ $template->name }} preview" class="w-full h-full object-cover object-top">
            @else
                <div class="w-full h-full grid place-items-center text-gray-400 text-sm">No preview image yet</div>
            @endif
        </div>
    </div>

    {{-- description --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-5 mb-4">
        <h2 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">About this template</h2>
        <p class="text-[14px] text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">{{ $template->description ?: $template->short_description }}</p>
        @if ($template->tags)
            <div class="flex flex-wrap gap-1.5 mt-3">
                @foreach ($template->tags as $tag)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- what's included --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-5 mb-4">
        <h2 class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">What's included</h2>
        <div class="flex flex-wrap gap-4 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"/></svg>
                </span>
                <span class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $included['pages']->count() }} {{ Str::plural('page', $included['pages']->count()) }}</span>
            </div>
            @if ($included['fonts'])
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h16M6 16l6-12 6 12M8.5 12h7"/></svg>
                </span>
                <span class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $included['fonts'] }} {{ Str::plural('font', $included['fonts']) }}</span>
            </div>
            @endif
            @if ($included['has_theme'])
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485"/></svg>
                </span>
                <span class="text-[13px] font-bold text-gray-800 dark:text-gray-100">Colour theme &amp; styles</span>
            </div>
            @endif
        </div>
        @if ($included['pages']->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($included['pages'] as $pg)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300">{{ $pg }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- works with these features --}}
    @if ($requiredFeatures->isNotEmpty())
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-5 mb-4">
        <h2 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Works with these features</h2>
        @foreach ($requiredFeatures as $f)
            <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                <span class="w-4 h-4 rounded-full grid place-items-center {{ $f['has'] ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-white/[0.12]' }}">
                    @if ($f['has'])<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                </span>
                <span class="text-[13.5px] font-bold text-gray-800 dark:text-gray-100">{{ $f['name'] }}</span>
                <span class="text-[11.5px] text-gray-400">{{ $f['has'] ? 'You have this' : 'Turned on when applied' }}</span>
            </div>
        @endforeach
        @if ($refSite)<p class="mt-2 text-[11px] text-gray-400">Compared against your site “{{ $refSite->name }}”.</p>@endif
    </div>
    @endif

    {{-- more from creator --}}
    @if ($moreFromCreator->isNotEmpty())
    <div class="mb-4">
        <h2 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">More from {{ $template->creator?->name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($moreFromCreator as $m)
                <a href="{{ route('marketplace.template', [$site->name, $m->slug]) }}" class="fx bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden">
                    <span class="block aspect-[4/3] bg-gray-100 dark:bg-white/[0.05]">
                        @if ($m->thumbnail_url)<img src="{{ $m->thumbnail_url }}" class="w-full h-full object-cover object-top" alt="">@endif
                    </span>
                    <span class="block p-2.5">
                        <span class="block text-[12.5px] font-extrabold text-gray-900 dark:text-white truncate">{{ $m->name }}</span>
                        <span class="block text-[11px] {{ $m->isFree() ? 'text-emerald-600 font-bold' : 'text-gray-400' }}">{{ $m->isFree() ? 'Free' : $money($m->price_cents, $m->currency) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

    {{-- ══ RIGHT rail: the buy panel + summary ══ --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $template->category ?: 'Template' }}</p>
            <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $template->name }}</h3>
            <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-0.5">by <span class="font-bold" style="color:var(--primary)">{{ $template->creator?->name ?? 'Olux Studio' }}</span></p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mt-2">{{ $template->short_description }}</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-3">{{ $template->isFree() ? 'Free' : $money($template->price_cents, $template->currency) }}</p>

            <div class="mt-3 space-y-2">
                @if ($inLibrary)
                    <div class="w-full min-h-[48px] rounded-xl grid place-items-center text-[14px] font-bold bg-emerald-500 text-white">In library ✓</div>
                    <a href="{{ route('marketplace', $site->name) }}?tab=library" class="fx block w-full min-h-[44px] leading-[44px] text-center rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">Use on a site</a>
                @elseif ($template->isFree())
                    <button wire:click="addToLibrary" class="fx w-full min-h-[48px] rounded-xl text-[14px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Add to library</button>
                @else
                    <button wire:click="buy" class="fx w-full min-h-[48px] rounded-xl text-[14px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Buy and add to library</button>
                @endif
                @if ($preview)
                    <x-preview-button :href="$preview" label="Live preview" class="w-full" />
                @endif
            </div>
            <p class="mt-3 text-[11px] text-gray-400">{{ $licence }}</p>
        </div>

        @if ($template->versions->isNotEmpty())
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Version history</h3>
            @foreach ($template->versions as $v)
                <div class="py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <p class="text-[12.5px] font-bold text-gray-800 dark:text-gray-100">{{ $v->version ?? 'v'.$v->id }} <span class="font-normal text-gray-400">· {{ $v->created_at?->format('j M Y') }}</span></p>
                    @if ($v->changelog)<p class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate" title="{{ $v->changelog }}">{{ $v->changelog }}</p>@endif
                </div>
            @endforeach
        </div>
        @endif
    </x-slot:quick>
</x-tri-layout>
