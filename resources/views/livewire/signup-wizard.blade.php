<div class="{{ $embedded ? 'w-full flex-1 flex flex-col' : 'w-full max-w-3xl mx-auto px-4 py-8 sm:py-12' }}">
    {{-- Same 4-step bar as the register panel: Account → Verify email → Your business → Done.
         Internal wizard steps are account(1) business(2) verify(3) done(4); map to the display order. --}}
    <div class="{{ $embedded ? 'signup-header pb-4' : 'mb-8' }}">
        <x-signup-steps :current="[1 => 1, 2 => 3, 3 => 2, 4 => 4][$step] ?? $step" />
    </div>

    <div class="{{ $embedded ? 'flex-1 flex flex-col justify-center py-4' : 'bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-6 sm:p-8' }}">

        {{-- ── 1 · Account ── --}}
        @if ($step === 1)
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Let’s get your site live</h1>
            <p class="text-sm text-gray-400 mt-1">Free for {{ $trialDays }} days, no card needed. Three fields and your site is being built.</p>
            @php $providers = $this->socialProviders(); @endphp
            @if ($providers)
                <div class="mt-6 grid {{ count($providers) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                    @foreach ($providers as $p)
                        <a href="{{ route('social.redirect', ['provider' => $p, 'intent' => 'signup']) }}"
                           class="flex items-center justify-center gap-2 border border-gray-200 dark:border-white/[0.1] rounded-xl py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
                            @if ($p === 'google')
                                <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                Continue with Google
                            @else
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Continue with Facebook
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="flex items-center gap-3 my-5 text-[11px] font-semibold uppercase tracking-wider text-gray-300 dark:text-gray-600">
                    <span class="flex-1 h-px bg-current opacity-40"></span> or enter your details <span class="flex-1 h-px bg-current opacity-40"></span>
                </div>
            @endif
            <form wire:submit="createAccount" class="{{ $providers ? '' : 'mt-6' }} space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Your name</label>
                    <input wire:model="name" type="text" autocomplete="name" autofocus class="olx-field" placeholder="Jane Smith">
                    @error('name') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Email</label>
                    <input wire:model="email" type="email" autocomplete="email" class="olx-field" placeholder="jane@example.com">
                    @error('email') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Password</label>
                    <input wire:model="password" type="password" autocomplete="new-password" class="olx-field" placeholder="At least 8 characters">
                    @error('password') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Confirm password</label>
                    <input wire:model="password_confirmation" type="password" autocomplete="new-password" class="olx-field" placeholder="Type it again">
                </div>
                <button type="submit" class="olx-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createAccount">Continue →</span>
                    <span wire:loading wire:target="createAccount">Creating your account…</span>
                </button>
                <p class="text-center text-xs text-gray-400">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-indigo-500 hover:underline">Sign in</a></p>
            </form>

        {{-- ── 2 · Business ── --}}
        @elseif ($step === 2)
            <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-500 mb-1">Step 3 of 4</p>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Your business</h1>
            <p class="text-sm text-gray-400 mt-1">We’ll set up the right pages and tools for it. Template, colours, domain and features are all chosen from your dashboard afterwards.</p>
            <form wire:submit="createSite" class="mt-6 space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">What kind of business?</label>
                    <div class="grid grid-cols-2 {{ $embedded ? '' : 'sm:grid-cols-3' }} gap-2">
                        @foreach ($types as $key => $meta)
                            <label class="olx-type {{ $type === $key ? 'is-on' : '' }} flex items-center gap-2 px-3 py-2.5 rounded-xl border cursor-pointer text-sm font-semibold transition-colors
                                          {{ $type === $key ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:border-indigo-300' }}">
                                <input type="radio" wire:model.live="type" value="{{ $key }}" class="sr-only">
                                <span class="text-lg leading-none">{{ $meta['icon'] ?? '•' }}</span>
                                <span class="truncate">{{ $meta['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('type') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Business name</label>
                    <input wire:model.live.debounce.400ms="business" type="text" class="olx-field" placeholder="Jane’s Salon">
                    @error('business') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">What is the site for? <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea wire:model="purpose" rows="2" class="olx-field" placeholder="e.g. Take bookings for my two-chair barbershop in Leeds and show off our work"></textarea>
                    @error('purpose') <p class="olx-err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Your web address</label>
                    <div class="olx-wrap flex items-stretch rounded-xl border border-gray-200 dark:border-white/[0.08] overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500/40">
                        <input wire:model.live.debounce.400ms="subdomain" type="text" class="flex-1 min-w-0 px-3.5 py-2.5 text-sm bg-transparent outline-none" placeholder="janes-salon" autocapitalize="off" spellcheck="false">
                        <span class="olx-suffix px-3 py-2.5 text-sm text-gray-400 bg-gray-50 dark:bg-white/[0.04] border-l border-gray-200 dark:border-white/[0.08] whitespace-nowrap">.{{ $base ?: 'oluxstudio.com' }}</span>
                    </div>
                    @if ($subdomain !== '' && $available !== null)
                        <p class="mt-1 text-xs font-semibold {{ $available ? 'text-emerald-600' : 'text-rose-500' }}">
                            {{ $available ? '✓ Available' : '✕ Taken or not allowed — try another' }}
                        </p>
                    @endif
                    @error('subdomain') <p class="olx-err">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-400">Live instantly at this address. Buy or connect your own domain any time from the dashboard.</p>
                </div>
                <button type="submit" class="olx-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createSite">Build my site →</span>
                    <span wire:loading wire:target="createSite">Building your site…</span>
                </button>
            </form>

        {{-- ── 3 · Verify ── --}}
        @elseif ($step === 3)
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Confirm your email</h1>
            <p class="text-sm text-gray-400 mt-1">{{ $business ?: 'Your site' }} is built. We sent a 6-digit code to <strong class="text-gray-700 dark:text-gray-200">{{ auth()->user()?->email }}</strong> — enter it to open your dashboard.</p>
            <form wire:submit="verify" class="mt-6">
                <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                       class="w-44 mx-auto block text-center text-2xl font-extrabold tracking-[0.35em] py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] focus:outline-none focus:ring-2 focus:ring-indigo-500/40" placeholder="123456">
                @error('code') <p class="olx-err text-center mt-2">{{ $message }}</p> @enderror
                @if (session('code-resent')) <p class="mt-2 text-center text-xs font-semibold text-emerald-600">{{ session('code-resent') }}</p> @endif
                <button type="submit" class="olx-primary mt-5" wire:loading.attr="disabled">Verify &amp; continue</button>
                <p class="text-center text-xs text-gray-400 mt-3">Didn’t get it? <button type="button" wire:click="resend" class="font-semibold text-indigo-500 hover:underline">Send a new code</button></p>
            </form>

        {{-- ── 4 · Done ── --}}
        @else
            @php $url = $this->siteUrl(); @endphp
            <div class="text-center">
                <span class="inline-grid place-items-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 text-xl mb-3">✓</span>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $business ?: 'Your site' }} is ready</h1>
                @if ($url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-block mt-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ $url }}</a>
                @endif
                <p class="text-sm text-gray-400 mt-3">Your {{ $trialDays }}-day free trial has started — no card needed. We’ve added a checklist to your Tasks: domain name, template, colour theme and the features your site needs.</p>
                <a href="{{ $this->dashboardUrl() ?? route('home') }}" class="olx-primary mt-6 inline-block">Open my dashboard →</a>
                @if ($this->todosUrl())
                    <a href="{{ $this->todosUrl() }}" class="block mt-3 text-sm font-semibold text-indigo-500 hover:underline">See my setup checklist →</a>
                @endif
            </div>
        @endif
    </div>

    @if ($embedded)
        <p class="text-center text-sm text-gray-400 pt-4 mt-auto">Signed in as {{ auth()->user()?->email }} · <a href="{{ route('logout') ?? '#' }}" class="font-semibold text-indigo-500 hover:underline" onclick="event.preventDefault(); document.getElementById('signup-logout').submit();">Not you?</a></p>
        <form id="signup-logout" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    @endif

    <style>
        .olx-field { width:100%; padding:.65rem .9rem; font-size:.875rem; border-radius:.75rem; border:1px solid rgba(0,0,0,.12); background:rgba(0,0,0,.02); outline:none; }
        .olx-field:focus { box-shadow:0 0 0 2px rgba(99,102,241,.35); border-color:transparent; }
        .dark .olx-field { border-color:rgba(255,255,255,.1); background:rgba(255,255,255,.04); color:#fff; }
        .olx-err { margin-top:.25rem; font-size:.75rem; color:#ef4444; }
        .olx-primary { display:block; width:100%; text-align:center; padding:.7rem 1.25rem; border-radius:.75rem; font-weight:700; font-size:.875rem; color:#fff; background:#4f46e5; }
        .olx-primary:hover { background:#4338ca; }
        .olx-primary:disabled { opacity:.6; }
    </style>
</div>
