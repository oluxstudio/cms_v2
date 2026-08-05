<div class="w-full max-w-md mx-auto">
    @if (! $invitation)
        {{-- ── Invalid / expired / already used ── --}}
        <div class="text-center space-y-4">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-rose-500/10 flex items-center justify-center">
                <svg class="w-7 h-7 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 5c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"/></svg>
            </div>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Invitation not valid</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">This invitation link has expired, was revoked, or has already been used. Ask the account owner to send you a new one.</p>
            <a href="{{ route('login') }}" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Go to sign in</a>
        </div>
    @else
        <div class="space-y-6">
            <div class="text-center space-y-2">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-indigo-500/10 flex items-center justify-center">
                    <svg class="w-7 h-7 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-1-7.87"/></svg>
                </div>
                <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">Join {{ $invitation->account->name }}'s team</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($invitation->inviter) {{ $invitation->inviter->name }} invited @else You've been invited @endif
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $invitation->email }}</span> to join as
                    <span class="font-semibold text-indigo-500">{{ $invitation->role->name }}</span>.
                </p>
            </div>

            @if ($error)
                <p class="text-sm text-rose-500 text-center">{{ $error }}</p>
            @endif

            @auth
                @if (mb_strtolower(auth()->user()->email) === $invitation->email)
                    {{-- Logged in as the invitee — one click. --}}
                    <button wire:click="acceptAsCurrentUser"
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                        Accept invitation as {{ auth()->user()->name }}
                    </button>
                @else
                    {{-- Wrong session — never attach the membership to a different email. --}}
                    <div class="text-center space-y-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">You're signed in as <span class="font-semibold">{{ auth()->user()->email }}</span>, but this invitation was sent to <span class="font-semibold">{{ $invitation->email }}</span>.</p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-white/10 text-sm font-semibold text-gray-600 dark:text-gray-300">Sign out and switch account</button>
                        </form>
                    </div>
                @endif
            @else
                @if ($existingUser)
                    {{-- Existing account — confirm the password to link it. --}}
                    <form wire:submit="acceptWithPassword" class="space-y-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center">An account already exists for this email — enter its password to accept.</p>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
                            <input wire:model="password" type="password" required
                                   class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            @error('password')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Sign in &amp; accept</button>
                    </form>
                @else
                    {{-- Brand-new user — the token verified the email; just set up the account. --}}
                    <form wire:submit="acceptAndRegister" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Your name</label>
                            <input wire:model="name" type="text" required
                                   class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Choose a password</label>
                            <input wire:model="password" type="password" required
                                   class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            @error('password')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">Confirm password</label>
                            <input wire:model="password_confirmation" type="password" required
                                   class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        </div>
                        <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Create account &amp; join</button>
                        <p class="text-[11px] text-gray-400 text-center">Your email is verified automatically — this link was sent to {{ $invitation->email }}.</p>
                    </form>
                @endif
            @endauth
        </div>
    @endif
</div>
