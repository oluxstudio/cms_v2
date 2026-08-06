<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.bare')] class extends Component {

    // — Login fields
    public string $loginEmail = '';
    public string $loginPassword = '';
    public bool   $remember = false;
    public bool   $showLoginPassword = false;

    // — Register fields
    public string $name = '';
    public string $registerEmail = '';
    public string $registerPassword = '';
    public string $registerPasswordConfirmation = '';
    public bool   $showRegisterPassword = false;

    public function login(): void
    {
        $this->validate([
            'loginEmail'    => ['required', 'string', 'email'],
            'loginPassword' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'loginEmail' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }

    public function register(): void
    {
        $validated = $this->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'registerEmail'                => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class . ',email'],
            'registerPassword'             => ['required', 'string', 'min:8', 'same:registerPasswordConfirmation'],
            'registerPasswordConfirmation' => ['required', 'string'],
        ], [
            'registerPassword.same' => 'Passwords do not match.',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['registerEmail'],
            'password' => Hash::make($validated['registerPassword']),
        ]);

        event(new Registered($user));
        Auth::login($user);

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }
        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());
        throw ValidationException::withMessages([
            'loginEmail' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->loginEmail) . '|' . request()->ip());
    }
}; ?>

<div
    x-data="{ mode: 'login' }"
    class="auth-screen min-h-screen flex items-center justify-center p-4"
>
    <div class="auth-card w-full max-w-[900px] rounded-3xl shadow-2xl overflow-hidden relative">

        {{-- ════════════════════════════════════
             FORM PANEL — slides left ↔ right
        ════════════════════════════════════ --}}
        <div class="absolute top-0 bottom-0 w-1/2 flex flex-col justify-center px-10 py-20 z-10
                    transition-all duration-500 ease-in-out"
             :class="mode === 'login' ? 'left-0' : 'left-1/2'">

            {{-- Logo --}}
            <div class="flex items-center gap-2 mb-8">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(120deg,var(--primary),var(--primary-2))">
                    <x-app-logo-icon class="w-4 h-4 fill-current text-white" />
                </div>
                <span class="font-bold text-lg auth-logo-text">
                    Olux CMS<span style="color:var(--primary)">.</span>
                </span>
            </div>

            {{-- ── LOGIN FORM ── --}}
            <div x-show="mode === 'login'" x-transition:enter="transition-opacity duration-300 delay-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-150" x-transition:leave-end="opacity-0">

                <h1 class="text-[26px] mb-1">Welcome Back</h1>
                <p class="text-sm text-gray-400 mb-6">Let's login to your studio account</p>

                {{-- Social buttons — 2 col grid --}}
                <div class="grid grid-cols-2 gap-2 mb-5">
                    {{-- Google --}}
                    <a href="{{ route('social.redirect', 'google') }}"
                       class="flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Google
                    </a>
                    {{-- Facebook --}}
                    <a href="{{ route('social.redirect', 'facebook') }}"
                       class="flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="#1877F2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        Facebook
                    </a>
                    {{-- X / Twitter --}}
                    <a href="{{ route('social.redirect', 'twitter') }}"
                       class="flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="#000000">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.256 5.628 5.909-5.628zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        X (Twitter)
                    </a>
                    {{-- Instagram --}}
                    <a href="{{ route('social.redirect', 'instagram') }}"
                       class="flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24">
                            <defs>
                                <linearGradient id="ig-grad" x1="0%" y1="100%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#f09433"/>
                                    <stop offset="25%" style="stop-color:#e6683c"/>
                                    <stop offset="50%" style="stop-color:#dc2743"/>
                                    <stop offset="75%" style="stop-color:#cc2366"/>
                                    <stop offset="100%" style="stop-color:#bc1888"/>
                                </linearGradient>
                            </defs>
                            <path fill="url(#ig-grad)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                        Instagram
                    </a>
                    {{-- TikTok — full width --}}
                    <a href="{{ route('social.redirect', 'tiktok') }}"
                       class="col-span-2 flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="#000000">
                            <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V9.05a8.16 8.16 0 004.77 1.52V7.12a4.85 4.85 0 01-1-.43z"/>
                        </svg>
                        TikTok
                    </a>
                </div>

                <div class="relative flex items-center gap-3 mb-5">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs text-gray-400">Or</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <form wire:submit="login" class="flex flex-col gap-3.5">
                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Email</label>
                        <input wire:model="loginEmail" type="email" required autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('loginEmail') border-red-400 @enderror" />
                    </div>
                    @error('loginEmail') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Password</label>
                        <input wire:model="loginPassword" :type="$wire.showLoginPassword ? 'text' : 'password'"
                            required autocomplete="current-password" placeholder="••••••••••••"
                            class="w-full px-4 pt-6 pb-2.5 pr-12 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('loginPassword') border-red-400 @enderror" />
                        <button type="button" wire:click="$toggle('showLoginPassword')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('loginPassword') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input wire:model="remember" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                            <span class="text-sm text-gray-600">Remember me</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-medium hover:underline" style="color:var(--penta)">Forgot Password?</a>
                        @endif
                    </div>

                    <button type="submit" class="w-full py-3.5 text-white font-semibold rounded-xl transition-all mt-1 auth-submit">
                        <span wire:loading.remove wire:target="login">Login</span>
                        <span wire:loading wire:target="login">Logging in…</span>
                    </button>
                </form>

                <p class="text-center text-sm text-gray-400 mt-5">
                    Don't have an account?
                    <button type="button" @click="mode = 'register'"
                        class="font-semibold hover:underline" style="color:var(--primary)">Sign Up</button>
                </p>
            </div>

            {{-- ── REGISTER FORM ── --}}
            <div x-show="mode === 'register'" x-transition:enter="transition-opacity duration-300 delay-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-150" x-transition:leave-end="opacity-0"
                 x-cloak>

                <h1 class="text-[26px] mb-1">Create Account</h1>
                <p class="text-sm text-gray-400 mb-6">Sign up to get started with Olux CMS</p>

                <form wire:submit="register" class="flex flex-col gap-3.5">
                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Full Name</label>
                        <input wire:model="name" type="text" required autocomplete="name"
                            placeholder="John Doe"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('name') border-red-400 @enderror" />
                    </div>
                    @error('name') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Email</label>
                        <input wire:model="registerEmail" type="email" required autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerEmail') border-red-400 @enderror" />
                    </div>
                    @error('registerEmail') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Password</label>
                        <input wire:model="registerPassword" :type="$wire.showRegisterPassword ? 'text' : 'password'"
                            required autocomplete="new-password" placeholder="••••••••••••"
                            class="w-full px-4 pt-6 pb-2.5 pr-12 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerPassword') border-red-400 @enderror" />
                        <button type="button" wire:click="$toggle('showRegisterPassword')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('registerPassword') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Confirm Password</label>
                        <input wire:model="registerPasswordConfirmation" type="password"
                            required autocomplete="new-password" placeholder="••••••••••••"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerPasswordConfirmation') border-red-400 @enderror" />
                    </div>
                    @error('registerPasswordConfirmation') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <button type="submit" class="w-full py-3.5 text-white font-semibold rounded-xl transition-all mt-1 auth-submit">
                        <span wire:loading.remove wire:target="register">Create Account</span>
                        <span wire:loading wire:target="register">Creating…</span>
                    </button>
                </form>

                <p class="text-center text-sm text-gray-400 mt-5">
                    Already have an account?
                    <button type="button" @click="mode = 'login'"
                        class="font-semibold hover:underline" style="color:var(--penta)">Login</button>
                </p>
            </div>
        </div>

        {{-- ════════════════════════════════════
             HERO PANEL — slides right ↔ left
        ════════════════════════════════════ --}}
        <div class="absolute top-0 bottom-0 w-1/2 transition-all duration-500 ease-in-out"
             :class="mode === 'login' ? 'left-1/2' : 'left-0'">
            <div class="absolute inset-0 m-3 rounded-2xl overflow-hidden"
                 style="background:linear-gradient(160deg,var(--primary) 0%,var(--primary-3) 62%,var(--primary-4) 100%)">

                {{-- Grid overlay --}}
                <div class="absolute inset-0 opacity-[0.07]"
                     style="background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);background-size:36px 36px"></div>

                {{-- Drifting brand shapes (landing-style ambient) --}}
                <span class="auth-drift" style="top:12%;left:12%;width:16px;height:16px;--d:16s"></span>
                <span class="auth-drift" style="top:70%;left:80%;width:26px;height:26px;--d:22s"></span>
                <span class="auth-drift dot" style="top:30%;left:78%;width:12px;height:12px;--d:19s"></span>
                <span class="auth-drift dot" style="top:82%;left:18%;width:20px;height:20px;--d:25s"></span>

                {{-- What the studio actually does — a live dashboard feed mock --}}
                <div class="absolute inset-x-8 top-14 space-y-2.5">
                    <div class="auth-feed-row">New estimate request <small>2 min ago</small></div>
                    <div class="auth-feed-row">Booking confirmed — £120 <small>1 h ago</small></div>
                    <div class="auth-feed-row">Invoice INV-014 paid <small>3 h ago</small></div>
                    <div class="auth-feed-row">New contact captured <small>today</small></div>
                    <div class="auth-feed-row">Site published — live with SSL <small>today</small></div>
                </div>

                {{-- Tagline --}}
                <div class="absolute bottom-8 left-0 right-0 text-center px-8">
                    <p class="text-white text-base font-semibold leading-snug" x-show="mode === 'login'">
                        Build, manage, and publish<br>beautiful sites with ease.
                    </p>
                    <p class="text-white text-base font-semibold leading-snug" x-show="mode === 'register'" x-cloak>
                        Join thousands of creators<br>building with Olux CMS.
                    </p>
                    <p class="text-white/40 text-xs mt-2">Your complete website studio.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    /* ── Landing-page theme applied to auth ─────────────────────────────── */
    @font-face { font-family: 'junegull'; src: url('/fonts/junegull.otf'); font-display: swap; }
    @font-face { font-family: 'garet'; font-weight: 400; src: url('/fonts/Garet-Book.woff2') format('woff2'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 400; src: url('/fonts/Comfortaa-Regular.ttf'); font-display: swap; }
    @font-face { font-family: 'comforta'; font-weight: 700; src: url('/fonts/Comfortaa-Bold.ttf'); font-display: swap; }

    .auth-screen {
        --primary: #e38704; --primary-2: #f77315; --primary-3: #5e3802; --primary-4: #3a2301;
        --penta: #fbbf24; --bg: #120f14; --on-bg: #f8f5f2; --on-bg-soft: rgba(248,245,242,.87);
        --surface: #1b1620; --line-inv: rgba(255,255,255,.12);
        background: var(--bg); font-family: 'garet', sans-serif; color: var(--on-bg);
    }
    .auth-card { background: var(--surface); border: 1px solid var(--line-inv); min-height: 710px; }
    .auth-screen h1 { font-family: 'junegull', 'trebuchet ms', sans-serif; text-transform: uppercase; color: var(--on-bg); font-weight: 400; }
    .auth-logo-text { font-family: 'junegull', sans-serif; color: var(--on-bg); }
    .auth-screen label, .auth-screen .text-xs, .auth-screen .text-sm { font-family: 'comforta', sans-serif; }

    /* Inputs on dark */
    .auth-screen input[type="email"], .auth-screen input[type="password"], .auth-screen input[type="text"] {
        background: rgba(255,255,255,.05); border-color: var(--line-inv); color: var(--on-bg);
    }
    .auth-screen input::placeholder { color: rgba(248,245,242,.55); }

    /* Social buttons + divider on dark */
    .auth-screen .grid.grid-cols-2 a { border-color: var(--line-inv); color: var(--on-bg-soft); }
    .auth-screen .grid.grid-cols-2 a:hover { background: rgba(255,255,255,.06); border-color: var(--primary); }
    .auth-screen .h-px { background: var(--line-inv); }
    .auth-screen .text-gray-600, .auth-screen .text-gray-700 { color: var(--on-bg-soft); }

    /* Submit buttons: orange gradient with glow */
    .auth-submit { background: linear-gradient(120deg, var(--primary), var(--primary-2)); box-shadow: 0 12px 26px -12px rgba(227,135,4,.55); font-family: 'comforta', sans-serif; }
    .auth-submit:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* Hero panel extras */
    .auth-feed-row {
        display: flex; justify-content: space-between; align-items: center;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.15);
        border-radius: 12px; padding: 11px 15px; color: #fff;
        font-family: 'comforta', sans-serif; font-size: 12.5px; font-weight: 700;
    }
    .auth-feed-row small { opacity: .7; font-weight: 400; }
    .auth-drift { position: absolute; display: block; border-radius: 22%; background: rgba(255,255,255,.35); animation: auth-drift var(--d, 18s) ease-in-out infinite; }
    .auth-drift.dot { border-radius: 9999px; background: rgba(251,191,36,.55); }
    @keyframes auth-drift {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50%      { transform: translate(14px, -18px) rotate(25deg); }
    }
    @media (prefers-reduced-motion: reduce) { .auth-drift { animation: none; } }
</style>
