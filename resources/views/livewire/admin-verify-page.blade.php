<div class="max-w-md mx-auto px-4 py-14">
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-7 text-center">
        <span class="inline-flex w-11 h-11 rounded-full items-center justify-center mb-3"
              style="background:color-mix(in srgb, #6366f1 15%, transparent); color:#6366f1">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </span>

        @if ($enrolling)
            <h1 class="text-lg font-extrabold text-gray-900 dark:text-white">Set up two-factor</h1>
            <p class="text-sm text-gray-400 mt-1">The admin area requires an authenticator app. Scan this QR code with Google Authenticator, Authy or 1Password, then enter the 6-digit code it shows.</p>
            <div class="inline-block bg-white rounded-xl p-3 mt-5 border border-gray-100">{!! $qrSvg !!}</div>
            <p class="mt-3 text-[11px] text-gray-400">Can’t scan? Enter this key manually:<br>
                <code class="text-xs font-bold tracking-wider text-gray-600 dark:text-gray-300 select-all">{{ $secret }}</code></p>
        @else
            <h1 class="text-lg font-extrabold text-gray-900 dark:text-white">Admin verification</h1>
            <p class="text-sm text-gray-400 mt-1">Enter the 6-digit code from your authenticator app to open the admin area.</p>
        @endif

        <form wire:submit="verify" class="mt-5">
            <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                   placeholder="123 456" autofocus
                   class="w-40 mx-auto block text-center text-2xl font-extrabold tracking-[0.35em] py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            @error('code') <p class="mt-2 text-xs font-semibold text-rose-500">{{ $message }}</p> @enderror
            <button type="submit"
                    class="mt-4 w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
                {{ $enrolling ? 'Confirm & enable' : 'Verify' }}
            </button>
        </form>
    </div>
</div>
