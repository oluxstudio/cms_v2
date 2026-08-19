<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Olux CMS' }}</title>
    {{-- Theme init: runs synchronously before render to prevent FOUC --}}
    <script>
    (function(){
        var saved  = localStorage.getItem('theme');
        var server = '{{ Auth::check() ? Auth::user()->theme : "light" }}';
        var t = saved || server;
        function apply(t){
            var el = document.documentElement;
            if(t === 'dark'){ el.classList.add('dark'); }
            else if(t === 'system'){
                window.matchMedia('(prefers-color-scheme:dark)').matches
                    ? el.classList.add('dark') : el.classList.remove('dark');
            } else { el.classList.remove('dark'); }
        }
        apply(t);
    })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 dark:bg-gray-950 antialiased min-h-screen"
      x-data="{}"
      x-on:theme-changed.window="
          var t = $event.detail.theme;
          localStorage.setItem('theme', t);
          var el = document.documentElement;
          if(t === 'dark'){ el.classList.add('dark'); }
          else if(t === 'system'){
              window.matchMedia('(prefers-color-scheme:dark)').matches
                  ? el.classList.add('dark') : el.classList.remove('dark');
          } else { el.classList.remove('dark'); }
      ">

    {{-- ════════════════ TOP BAR (no sidebar) ════════════════ --}}
    @auth
    <header class="sticky top-0 z-40 h-16 border-b border-gray-100 dark:border-gray-800 flex items-center gap-4 px-6 bg-white dark:bg-gray-900"
            x-data="{ profileOpen: false }" @click.outside="profileOpen = false">

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:var(--primary)">
                <x-app-logo-icon class="w-4 h-4 fill-current text-white" />
            </div>
            <span class="text-gray-900 dark:text-white font-bold text-base tracking-tight hidden sm:block">Olux CMS</span>
        </a>

        {{-- Site menu (opt-in): pages like /settings show the SAME grouped
             nav as site pages — resolved from the site you last worked on. --}}
        @php
            $navSiteName = null;
            if ($withSiteNav ?? false) {
                $navSiteName = session('olux.last_site');
                if (! $navSiteName || ! \App\Models\Site::where('name', $navSiteName)->exists()) {
                    $navSiteName = \App\Models\Site::where('user_id', auth()->id())
                        ->orWhereHas('members', fn ($m) => $m->where('users.id', auth()->id()))
                        ->latest()->value('name');
                }
            }
        @endphp
        @if($navSiteName)
            <nav class="flex-1 min-w-0 flex items-center justify-center">
                <x-site-nav :site-name="$navSiteName" />
            </nav>
        @endif

        {{-- Account plan — always visible, links to upgrade --}}
        <x-plan-badge />

        {{-- Search filter --}}
        <div class="{{ $navSiteName ? 'hidden lg:block w-64' : 'flex-1 max-w-md mx-auto' }}">
            <div class="relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                    placeholder="Search your sites…"
                    x-on:input.debounce.300ms="$dispatch('site-search', { query: $event.target.value })"
                    class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>
        </div>

        {{-- Profile + logout --}}
        <div class="relative shrink-0">
            <button @click="profileOpen = !profileOpen"
                class="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <x-avatar
                    :src="Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : null"
                    :initials="Auth::user()->initials()"
                    size="w-9 h-9"
                    :ring="true" />
                <div class="text-left hidden sm:block">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-tight">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 leading-tight">{{ Auth::user()->job_title ?: 'Member' }}</p>
                </div>
                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 transition-transform" :class="profileOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="profileOpen" x-cloak
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100" x-transition:leave-end="opacity-0 translate-y-1"
                 class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-800 overflow-hidden z-50">

                <div class="px-4 py-3 bg-gradient-to-br from-indigo-50 dark:from-gray-800 to-white dark:to-gray-900 border-b border-gray-100 dark:border-gray-800">
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
                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-700 transition-colors font-medium">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Profile & Settings
                    </a>
                    <a href="{{ route('how-it-works') }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-700 transition-colors font-medium">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        How it works
                    </a>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-800"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-700 transition-colors font-medium">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    @endauth

    {{-- ════════════════ MAIN CONTENT ════════════════ --}}
    <main class="max-w-7xl mx-auto">
        {{ $slot }}
    </main>

@stack('scripts')
<style>[x-cloak]{display:none!important}</style>
    <x-confirm-modal />
    <x-upgrade-modal />
    <livewire:welcome-onboarding />
</body>
</html>
