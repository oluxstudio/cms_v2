<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <style>
        body { font-family: 'Instrument Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 antialiased flex items-center justify-center p-4">

<div class="w-full max-w-[900px] bg-white rounded-3xl shadow-2xl overflow-hidden flex" style="min-height:600px">

    {{-- ── Left: Form panel ── --}}
    <div class="flex-1 flex flex-col justify-center px-10 py-12">

        {{-- Logo --}}
        <div class="flex items-center gap-2 mb-8">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#1e2235">
                <x-app-logo-icon class="w-4 h-4 fill-current text-white" />
            </div>
            <span class="font-bold text-lg" style="color:#1e2235">Olux CMS<span style="color:var(--primary)">.</span></span>
        </div>

        <h1 class="text-[28px] font-bold mb-1" style="color:#111827">Welcome Back</h1>
        <p class="text-sm text-gray-400 mb-7">Let's login to your studio account</p>

        {{-- Social buttons --}}
        <div class="space-y-3 mb-5">
            <button type="button"
                class="w-full flex items-center justify-center gap-3 border border-gray-200 rounded-xl py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>
            <button type="button"
                class="w-full flex items-center justify-center gap-3 border border-gray-200 rounded-xl py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="#1877F2">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Continue with Facebook
            </button>
        </div>

        {{-- Divider --}}
        <div class="relative flex items-center gap-3 mb-5">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs text-gray-400">Or</span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        {{-- Form slot --}}
        {{ $slot }}
    </div>

    {{-- ── Right: Hero panel ── --}}
    <div class="hidden md:block relative m-3 rounded-2xl overflow-hidden" style="flex:1; background:linear-gradient(160deg,#3b4a6b 0%,#1e2235 60%,#0f1322 100%)">

        {{-- Grid overlay --}}
        <div class="absolute inset-0 opacity-[0.07]"
             style="background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);background-size:36px 36px"></div>

        {{-- Cityscape illustration --}}
        <div class="absolute inset-0 flex items-end justify-center pb-24 opacity-30">
            <svg viewBox="0 0 420 260" class="w-full" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="0"   y="60"  width="55"  height="200" fill="#6366f1" opacity=".6"/>
                <rect x="50"  y="90"  width="40"  height="170" fill="#818cf8" opacity=".5"/>
                <rect x="85"  y="30"  width="70"  height="230" fill="#4f46e5" opacity=".7"/>
                <rect x="150" y="10"  width="50"  height="250" fill="#3730a3" opacity=".8"/>
                <rect x="195" y="50"  width="65"  height="210" fill="#4f46e5" opacity=".6"/>
                <rect x="255" y="75"  width="50"  height="185" fill="#6366f1" opacity=".5"/>
                <rect x="300" y="100" width="60"  height="160" fill="#818cf8" opacity=".4"/>
                <rect x="355" y="80"  width="65"  height="180" fill="#4f46e5" opacity=".6"/>
                {{-- Windows --}}
                <rect x="10"  y="80"  width="8" height="8" fill="white" opacity=".4"/>
                <rect x="24"  y="80"  width="8" height="8" fill="white" opacity=".3"/>
                <rect x="10"  y="100" width="8" height="8" fill="white" opacity=".5"/>
                <rect x="95"  y="50"  width="9" height="9" fill="white" opacity=".4"/>
                <rect x="115" y="50"  width="9" height="9" fill="white" opacity=".5"/>
                <rect x="135" y="50"  width="9" height="9" fill="white" opacity=".3"/>
                <rect x="160" y="28"  width="9" height="9" fill="white" opacity=".5"/>
                <rect x="178" y="28"  width="9" height="9" fill="white" opacity=".4"/>
                <rect x="205" y="68"  width="9" height="9" fill="white" opacity=".5"/>
                <rect x="225" y="68"  width="9" height="9" fill="white" opacity=".3"/>
                {{-- Clouds --}}
                <ellipse cx="80"  cy="25" rx="38" ry="14" fill="white" opacity=".7"/>
                <ellipse cx="108" cy="16" rx="28" ry="11" fill="white" opacity=".8"/>
                <ellipse cx="300" cy="20" rx="32" ry="12" fill="white" opacity=".6"/>
                <ellipse cx="328" cy="12" rx="22" ry="10" fill="white" opacity=".8"/>
            </svg>
        </div>

        {{-- Door frame --}}
        <div class="absolute top-8 left-1/2 -translate-x-1/2 w-28 h-40 rounded-t-full"
             style="border:3px solid rgba(255,255,255,0.2)"></div>

        {{-- Bottom tagline --}}
        <div class="absolute bottom-8 left-0 right-0 text-center px-8">
            <p class="text-white text-base font-semibold leading-snug">
                Build, manage, and publish<br>beautiful sites with ease.
            </p>
            <p class="text-white/40 text-xs mt-2">Your complete website studio.</p>
        </div>
    </div>

</div>

@fluxScripts
</body>
</html>
