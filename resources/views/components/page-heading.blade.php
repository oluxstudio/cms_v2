{{--
    Standard page header — title + one-line description. Pass a `segment`
    to pull both from config/site_pages.php, or override with title/subtitle.

        <x-page-heading segment="collections" />
        <x-page-heading title="Pages" subtitle="…" />
--}}
@props(['segment' => null, 'title' => null, 'subtitle' => null])

@php
    $meta = $segment ? config("site_pages.{$segment}", []) : [];
    $heading = $title ?? ($meta['title'] ?? ucwords(str_replace('-', ' ', (string) $segment)));
    $intro = $subtitle ?? ($meta['description'] ?? null);
@endphp

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $heading }}</h1>
    @if ($intro)
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ $intro }}</p>
    @endif
</div>
