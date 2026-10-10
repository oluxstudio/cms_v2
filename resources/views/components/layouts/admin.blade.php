{{--
  Platform super-admin shell: the app top bar + a dark icon rail for moving
  between admin pages (desktop: floating pill on the left; phones: bottom
  bar). Pages inside use <x-tri-layout> like the rest of the app.

    <x-layouts.admin title="Templates — Olux"> … </x-layouts.admin>
--}}
@props(['title' => 'Platform admin — Olux'])

@php
    // Two groups: the business (people, plans, catalogue, money) and the
    // platform (infrastructure). Pages not built yet are skipped.
    $adminGroups = [
        [
            ['admin.dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['admin.accounts', 'Accounts', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin.plans', 'Plans', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
            ['admin.templates', 'Templates', 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
            ['admin.sales', 'Sales & payouts', 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
            ['admin.referrals', 'Referrals', 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
            ['admin.testimonials', 'Testimonials', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
            ['admin.addons', 'Add-ons', 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z'],
        ],
        [
            ['admin.domains', 'Domains', 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9'],
            ['admin.ai', 'AI usage', 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5'],
            ['admin.announcements', 'Announcements', 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
            ['admin.operations', 'Operations', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ],
    ];
    $adminGroups = array_values(array_filter(array_map(
        fn ($g) => array_values(array_filter($g, fn ($i) => \Illuminate\Support\Facades\Route::has($i[0]))), $adminGroups)));
    $adminNav = array_merge(...$adminGroups);
    $adminActive = fn (string $route) => request()->routeIs($route) || ($route === 'admin.accounts' && request()->routeIs('admin.account'));
@endphp

<x-layouts.home wide>
    <x-slot:title>{{ $title }}</x-slot>

    <style>
        .adm-rail { background: var(--foreground); color: var(--background); }
        .adm-rail a, .adm-rail button { color: color-mix(in srgb, var(--background) 72%, transparent); }
        .adm-rail a:hover, .adm-rail button:hover { color: var(--background); }
        .adm-rail .is-active { background: var(--background); color: var(--foreground); box-shadow: 0 2px 10px rgb(0 0 0 / .18); }
    </style>

    {{-- Desktop: two floating pills (main nav · back/logout), like the sample's left bar --}}
    <nav aria-label="Admin" class="hidden lg:flex fixed left-4 top-24 bottom-6 z-30 w-14 flex-col justify-between gap-3 overflow-y-auto no-scrollbar">
        <div class="flex flex-col gap-3">
        @foreach ($adminGroups as $group)
            <div class="adm-rail rounded-full p-1.5 flex flex-col items-center gap-2 shadow-lg">
                @foreach ($group as [$route, $label, $icon])
                    <a href="{{ route($route) }}" wire:navigate title="{{ $label }}"
                       class="w-11 h-11 shrink-0 rounded-full grid place-items-center transition-colors {{ $adminActive($route) ? 'is-active' : '' }}"
                       @if ($adminActive($route)) aria-current="page" @endif>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <span class="sr-only">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
        </div>
        <div class="adm-rail rounded-full p-1.5 flex flex-col items-center gap-2 shadow-lg">
            <a href="{{ route('home') }}" title="Back to the app" class="w-11 h-11 rounded-full grid place-items-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="sr-only">Back to the app</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Log out" class="w-11 h-11 rounded-full grid place-items-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span class="sr-only">Log out</span>
                </button>
            </form>
        </div>
    </nav>

    {{-- Phones: the same nav as a bar under the top bar (the bottom edge is
         taken by the tri-layout's pane switcher) --}}
    <nav aria-label="Admin" class="lg:hidden sticky top-16 z-30 mx-3 mt-3 adm-rail rounded-full p-1.5 flex items-center gap-1 shadow-lg overflow-x-auto no-scrollbar">
        @foreach ($adminNav as [$route, $label, $icon])
            <a href="{{ route($route) }}" wire:navigate
               class="shrink-0 h-11 px-3.5 rounded-full flex items-center justify-center gap-1.5 text-[12px] font-bold whitespace-nowrap {{ $adminActive($route) ? 'is-active' : '' }}">
                <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    {{-- Background as a separate fixed layer: wrapping the content in .app-bg
         (isolation: isolate) would trap drawers/modals under the top bar. --}}
    <div class="app-bg fixed inset-0 -z-10" aria-hidden="true"><x-bg-ambient /></div>
    <div class="min-h-[calc(100vh-4rem)] lg:pl-20 max-w-[110rem] mx-auto">
        {{ $slot }}
    </div>
</x-layouts.home>
