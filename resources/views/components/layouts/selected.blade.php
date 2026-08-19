<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? ($siteName ?? 'Dashboard') }}</title>
    {{-- Apply dark class before any CSS paints — prevents flash of light theme --}}
    <script>if(localStorage.getItem('olux_theme')==='dark')document.documentElement.classList.add('dark');</script>
    <script>
        // Right-rail drawer state, shared by the header buttons, the aside and
        // the mobile FAB. Pinned open by CSS at ≥4xl; a drawer below that.
        document.addEventListener('alpine:init', () => {
            Alpine.store('rail', {
                open: false,   // drawer visible (<4xl only — ≥4xl the rail is always shown)
                hub: true,     // Alerts/Messages/Todos hub section
                llm: false,    // assistant expanded (input bar is always pinned at the bottom)
                tab: 'alerts',
                openTab(tab) {
                    if (this.open && this.hub && this.tab === tab) { this.close(); return; }
                    this.tab = tab;
                    this.hub = true;
                    this.open = true;
                    if (window.Livewire) Livewire.dispatch('rail-tab', { tab });
                },
                openChat() { this.open = true; this.hub = false; this.llm = true; },
                close() { this.open = false; this.llm = false; this.hub = true; },
            });
        });
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        [x-cloak] { display: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Only transition theme changes triggered by the user, not the initial load */
        .theme-ready body { transition: background-color .2s, color .2s; }
    </style>
</head>

{{-- Alpine on <body> manages the dark-mode global only (sidebar is gone). --}}
<body class="h-full app-bg text-gray-900 dark:text-white"
      x-data="{ isDark: localStorage.getItem('olux_theme') === 'dark' }"
      x-init="
          document.documentElement.classList.toggle('dark', isDark);
          requestAnimationFrame(() => document.documentElement.classList.add('theme-ready'));
          $watch('isDark', v => {
              localStorage.setItem('olux_theme', v ? 'dark' : 'light');
              document.documentElement.classList.toggle('dark', v);
              window.dispatchEvent(new CustomEvent('olux:theme', { detail: { mode: v ? 'dark' : 'light' } }));
          });
      ">

{{-- Ambient colored background elements — sit between the gradient and content. --}}
<x-bg-ambient />

@php
    $currentSite = \App\Models\Site::where('name', $siteName)->first();

    // The grouped menu itself lives in ONE place: <x-site-nav> — same
    // component renders it here and on every other page that shows it.

    // Remember the working site: site-less pages (e.g. /settings) resolve
    // their menu from this.
    session(['olux.last_site' => $siteName]);

    // Right rail data (computed once here so the markup stays clean).
    $promptSiteId = $siteId ?? ($currentSite?->id);
    $railAlerts   = $currentSite ? $currentSite->contacts()->latest()->take(5)->get() : collect();

    // Unread counts for the header rail-buttons (same scoping as SiteRail::counts).
    $railCounts = null;
    if ($currentSite && auth()->check()) {
        $railUser = auth()->user();
        $railCounts = [
            'alerts' => \App\Models\Alert::visibleTo($currentSite, $railUser)->whereNull('read_at')->count(),
            'messages' => \App\Models\Message::visibleTo($currentSite, $railUser)->whereNull('read_at')
                ->where('sender_id', '!=', $railUser->id)->count(),
            'todos' => \App\Models\Todo::visibleTo($currentSite, $railUser)->where('status', 'open')->count(),
        ];
    }

    // Template apps available for the in-page "Generate" form (renderer to bundle).
    $genTemplates = collect(\App\Templates\TemplateAppRegistry::all())
        ->map(fn ($t) => ['key' => $t['key'], 'name' => $t['name']])->values()->all();
    $genCurrentKey = $currentSite?->renderTemplateKey();
    $canGenerate  = $currentSite && $currentSite->canManageTeam(auth()->user());
@endphp

<div class="h-full flex flex-col overflow-hidden">

    {{-- ════════════════════════ TOP HEADER (Lisso style) ════════════════════════ --}}
    <header class="shrink-0 px-4 pt-4 z-30">
        <div class="w-full flex items-center gap-4 h-16 px-4 sm:px-5
                    bg-white/90 dark:bg-[#1d1e2a]/90 backdrop-blur
                    rounded-2xl shadow-sm border border-white/60 dark:border-white/[0.05]">

            {{-- ── Logo (left) ── --}}
            <a href="{{ url($siteName.'/dashboard') }}" class="flex items-center gap-2 shrink-0">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm overflow-hidden bg-gray-900 dark:bg-white/10">
                    <img src="{{ Vite::asset('resources/images/icon.svg') }}" alt="Logo" class="w-5 h-5">
                </span>
                <span class="hidden sm:block text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">Olux<span class="text-indigo-500">.</span></span>
            </a>

            {{-- ── Menu (center): the shared grouped nav component ── --}}
            <nav class="flex-1 min-w-0 flex items-center justify-center">
                <x-site-nav :site-name="$siteName" />
            </nav>

            {{-- ── Right cluster ── --}}
            <div class="flex items-center gap-1 shrink-0">

                {{-- Account plan — always visible, links to upgrade --}}
                <x-plan-badge />

                {{-- Theme toggle --}}
                <button @click="isDark = !isDark"
                        class="w-9 h-9 flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors"
                        :title="isDark ? 'Light mode' : 'Dark mode'">
                    <svg x-show="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg x-show="!isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-white/10 mx-1"></div>

                @auth
                {{-- Profile dropdown --}}
                <div class="relative" x-data="{ profileOpen: false }" @click.outside="profileOpen = false">
                    <button @click="profileOpen = !profileOpen"
                            class="flex items-center gap-2 p-0.5 sm:pr-2 rounded-full hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                        <x-avatar
                            :src="Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : null"
                            :initials="Auth::user()->initials()"
                            size="w-8 h-8"
                            :ring="true" />
                        <span class="hidden sm:block text-sm font-semibold text-gray-800 dark:text-gray-100 max-w-[90px] truncate">{{ \Illuminate\Support\Str::of(Auth::user()->name)->explode(' ')->first() }}</span>
                        <svg class="hidden sm:block w-3.5 h-3.5 text-gray-400 transition-transform" :class="profileOpen ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="profileOpen" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-xl border border-gray-100 dark:border-white/[0.06] overflow-hidden z-50">

                        <div class="px-4 py-3 bg-gradient-to-br from-indigo-50 dark:from-white/[0.04] to-white dark:to-transparent border-b border-gray-100 dark:border-white/[0.05]">
                            <div class="flex items-center gap-3">
                                <x-avatar
                                    :src="Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : null"
                                    :initials="Auth::user()->initials()"
                                    size="w-10 h-10" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ Auth::user()->email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-1.5">
                            <a href="{{ route('settings') }}"
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-white/[0.05] hover:text-indigo-700 dark:hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Profile &amp; Settings
                            </a>
                            <a href="{{ route('home') }}"
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-white/[0.05] hover:text-indigo-700 dark:hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                All Sites
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Logout button (next to profile) --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Log out"
                            class="w-9 h-9 flex items-center justify-center rounded-full text-gray-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
                @endauth
            </div>
        </div>
    </header>

    {{-- ════════════════════════ BODY: editor · right rail ════════════════════════ --}}
    <div class="flex-1 min-h-0 flex overflow-hidden"
         x-data="{ detail: false }"
         @rail-open.window="detail = true">

        {{-- ── Editor (slot) ── --}}
        <main class="flex-1 min-h-0 flex flex-col overflow-hidden">

            {{-- Breadcrumb bar — Site › Page, on every page --}}
            @php
                $crumbSeg  = request()->segment(2) ?: 'dashboard';
                $crumbMeta = config("site_pages.{$crumbSeg}", ['title' => ucwords(str_replace('-', ' ', $crumbSeg))]);
            @endphp
            <div class="shrink-0 px-4 sm:px-6 py-2.5 flex items-center justify-between gap-3
                        bg-[#f7f3ee]/85 dark:bg-[#16171d]/85 backdrop-blur
                        border-b border-gray-200/70 dark:border-white/[0.05] z-20">
                <nav class="flex items-center gap-2 min-w-0 text-xs" aria-label="Breadcrumb">
                    <span class="w-2 h-2 rounded-full shrink-0 bg-emerald-500"></span>
                    <a href="{{ url($siteName.'/dashboard') }}" class="font-semibold text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 truncate">{{ ucwords(str_replace('-', ' ', $siteName)) }}</a>
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-gray-500 dark:text-gray-400 truncate" aria-current="page">{{ $crumbMeta['title'] }}</span>
                </nav>

                {{-- Rail tabs, right side of the breadcrumb bar: labeled pills
                     on ultra-wide (control the pinned rail), badge icons below
                     4xl (toggle the drawer). --}}
                @if ($railCounts !== null && !empty($promptSiteId))
                <div class="shrink-0 flex items-center">
                    @php
                        $railTabs = [
                            'alerts'   => ['Alerts', '#ef4444', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                            'messages' => ['Messages', '#6366f1', 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4-.8L3 20l1.3-3.9A7.4 7.4 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z'],
                            'todos'    => ['Todos', '#10b981', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ];
                    @endphp

                    {{-- ≥4xl: pill tab bar --}}
                    <div class="hidden 4xl:flex gap-1 bg-white/70 dark:bg-white/[0.05] rounded-2xl p-1">
                        @foreach ($railTabs as $railKey => [$railLabel, $railColor, $railPath])
                            <button type="button"
                                    @click="$store.rail.tab = '{{ $railKey }}'; Livewire.dispatch('rail-tab', { tab: '{{ $railKey }}' })"
                                    class="fx flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold transition-colors"
                                    :class="$store.rail.tab === '{{ $railKey }}'
                                        ? 'bg-gray-900 text-white shadow dark:bg-white dark:text-gray-900'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.05]'">
                                {{ $railLabel }}
                                @if (($railCounts[$railKey] ?? 0) > 0)
                                    <span class="text-[10px] font-bold px-1.5 rounded-full text-white" style="background:{{ $railColor }}">{{ $railCounts[$railKey] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    {{-- <4xl: icon-only badges toggling the drawer --}}
                    <div class="4xl:hidden flex items-center gap-0.5">
                        @foreach ($railTabs as $railKey => [$railLabel, $railColor, $railPath])
                            <button type="button" @click="$store.rail.openTab('{{ $railKey }}')"
                                    class="relative w-8 h-8 flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-white dark:hover:bg-white/[0.06] transition-colors"
                                    :class="$store.rail.open && $store.rail.hub && $store.rail.tab === '{{ $railKey }}' ? 'bg-white dark:bg-white/[0.08] text-gray-900 dark:text-white shadow-sm' : ''"
                                    title="{{ $railLabel }}">
                                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $railPath }}"/>
                                </svg>
                                @if (($railCounts[$railKey] ?? 0) > 0)
                                    <span class="absolute -top-0.5 -right-0.5 min-w-[15px] h-[15px] px-0.5 rounded-full text-[9px] font-bold text-white flex items-center justify-center"
                                          style="background:{{ $railColor }}">{{ $railCounts[$railKey] > 99 ? '99+' : $railCounts[$railKey] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Page content / detail pane --}}
            <div id="MainBody" class="flex-1 min-h-0 overflow-y-auto">

                {{-- Normal slot / rail detail. h-full so height propagates to
                     full-height pages; #MainBody's overflow-y-auto scrolls taller ones. --}}
                <div class="h-full">
                    <div x-show="!detail" class="h-full">{{ $slot }}</div>

                    @auth
                    @if(!empty($promptSiteId))
                    <div x-show="detail" x-cloak class="h-full">
                        <livewire:site-rail-detail :site-id="$promptSiteId" :key="'rail-detail-'.$promptSiteId" />
                    </div>
                    @endif
                    @endauth
                </div>
            </div>
        </main>


        {{-- ── Right rail: pinned at ≥4xl, slide-over drawer below ── --}}
        @auth
        @if(!empty($promptSiteId))
        <aside class="flex-col w-[440px] max-sm:w-full min-w-0 border-l border-gray-200 dark:border-white/[0.05]
                      bg-[#f7f3ee] dark:bg-[#16171d] overflow-hidden"
               :class="$store.rail.open
                   ? 'flex max-4xl:fixed max-4xl:right-0 max-4xl:top-0 max-4xl:bottom-0 max-4xl:z-50 max-4xl:shadow-2xl'
                   : 'hidden 4xl:flex'"
               x-cloak>

            {{-- Drawer header (below 4xl only): close --}}
            <div class="4xl:hidden shrink-0 flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-white/[0.05]">
                <span class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider"
                      x-text="$store.rail.hub ? $store.rail.tab : 'Polux · AI Assistant'"></span>
                <button type="button" @click="$store.rail.close()" title="Close panel"
                        class="fx w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-rose-600 hover:bg-white dark:hover:bg-white/[0.05]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Alerts · Messages · Todos hub. flex-1 next to an expanded
                 assistant (also flex-1) = the 50/50 split. --}}
            <div x-show="$store.rail.hub" class="flex-1 min-h-0">
                <livewire:site-rail :site-id="$promptSiteId" :key="'site-rail-'.$promptSiteId" />
            </div>

            {{-- ── Assistant: input bar pinned at the bottom; expands to fill
                 the rail when a prompt is sent (50/50 when the hub is open too).
                 The collapsed bar only exists ≥4xl — below that the FAB opens it. ── --}}
            <div class="flex-col border-t border-gray-200 dark:border-white/[0.05]"
                 :class="$store.rail.llm ? 'flex flex-1 min-h-0' : 'hidden 4xl:flex 4xl:shrink-0'"
                 @submit="$store.rail.llm = true">
                <div class="shrink-0 flex items-center justify-between px-4 py-2.5">
                    <span class="flex items-center gap-2 text-xs font-bold text-gray-900 dark:text-white">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 fx-pulse"></span> Polux · AI Assistant
                    </span>
                    <button type="button" @click="$store.rail.llm = !$store.rail.llm" class="fx w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-indigo-600 hover:bg-white dark:hover:bg-white/[0.05]"
                            :title="$store.rail.llm ? 'Collapse' : 'Expand'">
                        <svg class="w-4 h-4 transition-transform" :class="$store.rail.llm ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>
                {{-- Collapsed: capped height clips the chat log so only the
                     bottom input bar shows. Expanded: fills the section. --}}
                <div class="min-h-0 overflow-hidden"
                     :class="$store.rail.llm ? 'flex-1' : 'h-24 shrink-0'">
                    <livewire:site-prompt :site-id="$promptSiteId" :key="'site-prompt-'.$promptSiteId" />
                </div>
            </div>
        </aside>

        {{-- Below 4xl there is no inline assistant bar — this FAB opens it. --}}
        <button type="button" x-show="!$store.rail.open" x-cloak @click="$store.rail.openChat()"
                class="4xl:hidden fixed bottom-4 right-4 z-40 w-12 h-12 rounded-full text-white shadow-lg flex items-center justify-center"
                style="background:var(--primary)" title="Ask Polux">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4-.8L3 20l1.3-3.9A7.4 7.4 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z"/>
            </svg>
        </button>
        @endif
        @endauth
    </div>
</div>

{{-- ── Toast notifications ── --}}
@include('partials.toasts')

{{-- ── Route loading indicator (top bar + soft overlay) ── --}}
<div id="route-progress"></div>
<div id="route-overlay"><div class="spinner"></div></div>
<script>
(function () {
    const bar = document.getElementById('route-progress');
    const ov  = document.getElementById('route-overlay');
    let started = false;

    function start() {
        if (started) return;
        started = true;
        // restart the bar animation, then drive it toward the end
        bar.classList.remove('on');
        void bar.offsetWidth;            // reflow so the transition replays
        bar.classList.add('on');
        ov.classList.add('on');
    }
    function stop() {
        started = false;
        bar.classList.remove('on');
        ov.classList.remove('on');
    }

    // Internal full-page navigations
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (!a) return;
        if (a.target === '_blank' || a.hasAttribute('download')) return;
        if (a.hasAttribute('data-download-progress')) return; // handled by the download-progress component (no navigation)
        const href = a.getAttribute('href');
        if (!href || href[0] === '#' || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
        if (a.hasAttribute('@click') || a.hasAttribute('x-on:click') || a.hasAttribute('wire:click')) return;
        try { if (new URL(a.href).origin !== location.origin) return; } catch (_) { return; }
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey) return;
        start();
    }, true);

    // Real form posts (not Livewire/AJAX submits)
    document.addEventListener('submit', function (e) {
        const f = e.target;
        if (f.hasAttribute('wire:submit') || f.getAttribute('x-on:submit') || f.hasAttribute('@submit')) return;
        if (f.target === '_blank') return; // opens a new tab — this page never navigates
        start();
    }, true);

    // Hide when the new page is shown (covers bfcache restores too)
    window.addEventListener('pageshow', stop);
    window.addEventListener('pagehide', stop);
})();
</script>

<x-download-progress />
@stack('scripts')
    <x-confirm-modal />
    <x-upgrade-modal />
</body>
</html>
