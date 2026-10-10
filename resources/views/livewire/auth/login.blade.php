<?php

use App\Models\User;
use App\Services\SignupVerification;
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
    public string $registerPhone = '';
    public string $registerEmail = '';
    public string $registerPassword = '';
    public string $registerPasswordConfirmation = '';
    public bool   $showRegisterPassword = false;

    // — Email verification (OTP) — the account is only created after the code is entered
    public ?string $pendingToken = null;
    public string $pendingEmail = '';
    public string $code = '';

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

        // A plan chosen on the landing page sends the user straight to checkout;
        // otherwise land where this account belongs: an invited member goes to
        // their site's dashboard, an owner to the wizard or the site picker.
        $plan = session('intended_plan');
        $default = $plan
            ? route('account.subscription', ['plan' => $plan], absolute: false)
            : Auth::user()->landingUrl();

        $this->redirectIntended(default: $default, navigate: true);
    }

    /**
     * Step 1 — validate the details and email a code. NO account is created yet:
     * the pending sign-up is held server-side until the code is verified.
     */
    public function startVerification(): void
    {
        $validated = $this->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'registerPhone'                => ['required', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ()\-]{8,19}$/'],
            'registerEmail'                => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class . ',email'],
            'registerPassword'             => ['required', 'string', 'min:8', 'same:registerPasswordConfirmation'],
            'registerPasswordConfirmation' => ['required', 'string'],
        ], [
            'registerPassword.same' => 'Passwords do not match.',
            'registerPhone.regex' => 'Enter a phone number we can reach you on, e.g. 07700 900000.',
        ]);

        $this->pendingToken = app(SignupVerification::class)->start([
            'name'     => $validated['name'],
            'phone'    => preg_replace('/[^\d+]/', '', $validated['registerPhone']),
            'email'    => $validated['registerEmail'],
            'password' => $validated['registerPassword'],
        ]);
        $this->pendingEmail = $validated['registerEmail'];
        $this->code = '';
    }

    /**
     * Step 2 — verify the code and only NOW create the (already-verified) account.
     */
    public function verifyCode(): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $data = app(SignupVerification::class)->verify($this->pendingToken, $this->code);

        $user = User::create([
            'name'              => $data['name'],
            'phone'             => $data['phone'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'password_changed_at' => now(), // they chose it themselves
            'email_verified_at' => now(),
        ]);

        event(new Registered($user));
        Auth::login($user);
        $this->reset('pendingToken', 'pendingEmail', 'code');

        // A verified account → straight into the signup wizard (business → site → checklist).
        $plan = session('intended_plan');
        $default = $plan
            ? route('account.subscription', ['plan' => $plan], absolute: false)
            : Auth::user()->landingUrl();

        $this->redirectIntended(default: $default, navigate: true);
    }

    public function resendCode(): void
    {
        if ($this->pendingToken) {
            app(SignupVerification::class)->resend($this->pendingToken);
            session()->flash('code-resent', 'A new code is on its way.');
        }
    }

    /** Back to the sign-up form (wrong email, etc.). */
    public function restartSignup(): void
    {
        $this->reset('pendingToken', 'pendingEmail', 'code');
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
    x-data="{ mode: '{{ request()->query('mode') === 'register' ? 'register' : 'login' }}' }"
    class="auth-screen min-h-screen flex flex-col items-center justify-center p-4"
>
    <div class="auth-card w-full max-w-[65rem] rounded-3xl shadow-2xl overflow-hidden relative">

        {{-- ════════════════════════════════════
             FORM PANEL — slides left ↔ right
        ════════════════════════════════════ --}}
        <div class="relative w-full px-6 py-12 md:absolute md:top-0 md:bottom-0 md:w-1/2 md:px-7 md:py-4 md:overflow-y-auto
                    flex flex-col justify-start z-10 transition-all duration-500 ease-in-out"
             :class="mode === 'login' ? 'md:left-0' : 'md:left-1/2'">

            {{-- Logo --}}
            <div class="flex items-center gap-2 mb-8">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(120deg,var(--primary),var(--primary-2))">
                    <x-app-logo-icon class="w-4 h-4 fill-current text-white" />
                </div>
                <span class="font-bold text-lg auth-logo-text">
                    {{ config('app.name') }}<span style="color:var(--primary)">.</span>
                </span>
            </div>

            {{-- ── LOGIN FORM ── --}}
            <div x-show="mode === 'login'" x-transition:enter="transition-opacity duration-300 delay-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-150" x-transition:leave-end="opacity-0"
                 class="flex-1 flex flex-col justify-center">

                <h1 class="text-[26px] mb-1">Welcome Back</h1>
                <p class="text-sm text-gray-400 mb-6">Let's login to your studio account</p>

                {{-- Social sign-in: Google + Facebook only --}}
                <x-social-auth class="mb-5" />

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
                 x-cloak class="flex-1 flex flex-col">

                {{-- Wizard header — pinned to the top of the panel --}}
                <div class="signup-header pb-4">
                    <x-signup-steps :current="$pendingToken ? 2 : 1" />
                </div>

                {{-- Step content — vertically centred in the remaining space --}}
                <div class="flex-1 flex flex-col justify-center py-2">
                <h1 class="text-[26px] mb-1">{{ $pendingToken ? 'Check your email' : 'Create Account' }}</h1>
                <p class="text-sm text-gray-400 mb-5">{{ $pendingToken ? 'Step 2 of 4 — confirm it’s really you.' : 'Step 1 of 4 — your details. Next we verify your email, then set up your business.' }}</p>

                @if (! $pendingToken)
                {{-- Or create the account straight from Google / Facebook (email already verified there) --}}
                <x-social-auth intent="signup" class="mb-4" />
                <div class="relative flex items-center gap-3 mb-4">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs text-gray-400">Or sign up with email</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>
                <form wire:submit="startVerification" class="flex flex-col gap-3.5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <div>
                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Full Name</label>
                        <input wire:model="name" type="text" required autocomplete="name"
                            placeholder="John Doe"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('name') border-red-400 @enderror" />
                    </div>
                    @error('name') <p class="text-xs text-red-500 mt-1 px-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Phone</label>
                        <input wire:model="registerPhone" type="tel" required autocomplete="tel"
                            placeholder="+44 7700 900000"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerPhone') border-red-400 @enderror" />
                    </div>
                    @error('registerPhone') <p class="text-xs text-red-500 mt-1 px-1">{{ $message }}</p> @enderror
                    </div>
                    </div>

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Email</label>
                        <input wire:model="registerEmail" type="email" required autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerEmail') border-red-400 @enderror" />
                    </div>
                    @error('registerEmail') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <div>
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
                    @error('registerPassword') <p class="text-xs text-red-500 mt-1 px-1">{{ $message }}</p> @enderror
                    </div>
                    <div>

                    <div class="relative">
                        <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Confirm Password</label>
                        <input wire:model="registerPasswordConfirmation" type="password"
                            required autocomplete="new-password" placeholder="••••••••••••"
                            class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm bg-white transition-all @error('registerPasswordConfirmation') border-red-400 @enderror" />
                    </div>
                    @error('registerPasswordConfirmation') <p class="text-xs text-red-500 mt-1 px-1">{{ $message }}</p> @enderror
                    </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 text-white font-semibold rounded-xl transition-all mt-1 auth-submit">
                        <span wire:loading.remove wire:target="startVerification">Send verification code</span>
                        <span wire:loading wire:target="startVerification">Sending…</span>
                    </button>
                    <p class="text-center text-xs text-gray-400">By creating an account (with email, Google or Facebook) you agree to how we use your data in our <a href="{{ route('privacy') }}" target="_blank" class="font-semibold hover:underline" style="color:var(--penta)">Privacy Policy</a>.</p>
                </form>
                @else
                {{-- Step 2 — verify the emailed code (the account is created only after this) --}}
                <div class="flex flex-col gap-3.5">
                    <p class="text-sm text-gray-500 -mt-2">
                        We emailed a 6-digit code to
                        <span class="font-semibold text-gray-700">{{ \Illuminate\Support\Str::mask($pendingEmail, '•', 1, max(1, strpos($pendingEmail, '@') - 1)) }}</span>.
                        Enter it below to finish creating your account.
                    </p>
                    @if (session('code-resent'))
                        <p class="text-xs text-emerald-600 -mt-1 px-1">{{ session('code-resent') }}</p>
                    @endif
                    <form wire:submit="verifyCode" class="flex flex-col gap-3.5">
                        <div class="relative">
                            <label class="absolute left-4 top-2 text-xs text-gray-400 pointer-events-none">Verification code</label>
                            <input wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                                placeholder="123456" autofocus
                                class="w-full px-4 pt-6 pb-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-lg tracking-[0.5em] font-semibold bg-white transition-all @error('code') border-red-400 @enderror" />
                        </div>
                        @error('code') <p class="text-xs text-red-500 -mt-1 px-1">{{ $message }}</p> @enderror

                        <button type="submit" class="w-full py-3.5 text-white font-semibold rounded-xl transition-all mt-1 auth-submit">
                            <span wire:loading.remove wire:target="verifyCode">Verify &amp; create account</span>
                            <span wire:loading wire:target="verifyCode">Verifying…</span>
                        </button>
                    </form>
                    <div class="flex items-center justify-between text-sm">
                        <button type="button" wire:click="resendCode" class="font-medium text-indigo-600 hover:text-indigo-700">
                            <span wire:loading.remove wire:target="resendCode">Resend code</span>
                            <span wire:loading wire:target="resendCode">Sending…</span>
                        </button>
                        <button type="button" wire:click="restartSignup" class="text-gray-400 hover:text-gray-600">Change email</button>
                    </div>
                </div>
                @endif
                </div>

                {{-- Footer — pinned to the bottom of the panel --}}
                <p class="text-center text-sm text-gray-400 pt-4 mt-auto">
                    Already have an account?
                    <button type="button" @click="mode = 'login'"
                        class="font-semibold hover:underline" style="color:var(--penta)">Login</button>
                </p>
            </div>
        </div>

        {{-- ════════════════════════════════════
             HERO PANEL — slides right ↔ left
        ════════════════════════════════════ --}}
        <div class="hidden md:block absolute top-0 bottom-0 w-1/2 transition-all duration-500 ease-in-out"
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
                        Join thousands of creators<br>building with {{ config('app.name') }}.
                    </p>
                    <p class="text-white/40 text-xs mt-2">Your complete website studio.</p>
                </div>
            </div>
        </div>

    </div>
    <x-legal-footer class="pb-0" />
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
    .auth-card { background: var(--surface); border: 1px solid var(--line-inv); }
    @media (min-width: 768px) { .auth-card { min-height: 750px; } }
    .auth-screen h1 { font-family: 'junegull', 'trebuchet ms', sans-serif; text-transform: uppercase; color: var(--on-bg); font-weight: 400; }
    .auth-logo-text { font-family: 'junegull', sans-serif; color: var(--on-bg); }
    .auth-screen label, .auth-screen .text-xs, .auth-screen .text-sm { font-family: 'comforta', sans-serif; }

    /* Inputs on dark */
    .auth-screen input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), .auth-screen textarea, .auth-screen select {
        background: rgba(255,255,255,.05); border-color: var(--line-inv); color: var(--on-bg);
    }
    .auth-screen input::placeholder, .auth-screen textarea::placeholder { color: rgba(248,245,242,.55); }
    .auth-screen input:-webkit-autofill, .auth-screen input:-webkit-autofill:hover, .auth-screen input:-webkit-autofill:focus, .auth-screen textarea:-webkit-autofill {
        -webkit-box-shadow: 0 0 0 1000px #241d29 inset; -webkit-text-fill-color: var(--on-bg); caret-color: var(--on-bg); border-color: var(--line-inv); transition: background-color 9999s ease-out;
    }

    /* Social buttons + divider on dark */
    .auth-screen .grid.grid-cols-2 a { border-color: var(--line-inv); color: var(--on-bg-soft); }
    .auth-screen .grid.grid-cols-2 a:hover { background: rgba(255,255,255,.06); border-color: var(--primary); }
    .auth-screen .social-btn { background: rgba(255,255,255,.05); border-color: var(--line-inv); color: var(--on-bg); }
    .auth-screen .social-btn:hover { background: rgba(255,255,255,.1); border-color: var(--primary); }
    .auth-screen .h-px { background: var(--line-inv); }
    .auth-screen .signup-steps .step-bg { background: var(--surface); }
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
