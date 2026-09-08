<div class="min-h-screen">
    <header class="sticky top-0 z-30 bg-white/80 dark:bg-[#15161f]/80 backdrop-blur border-b border-gray-100 dark:border-white/[0.06]">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-extrabold text-gray-900 dark:text-white"><x-app-logo-icon class="w-6 h-6 fill-current" /> {{ config('app.name', 'Olux') }}</a>
            <span class="text-sm font-semibold text-gray-400">Checkout</span>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Switch between dark and light theme" title="Toggle theme"><span class="tt-sun">☀️</span><span class="tt-moon">🌙</span></button>
            
        </div>
    </header>

    <div class="max-w-3xl mx-auto px-4 py-10">
        <a href="{{ route('template.detail', $urlKey) }}" class="text-sm font-semibold text-indigo-500 hover:underline">← Back to {{ $card['name'] }}</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 mb-6">Buy “{{ $card['name'] }}”</h1>

        <div class="grid sm:grid-cols-[200px_1fr] gap-5 rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
            @if ($card['thumbnail'])
                <img src="{{ $card['thumbnail'] }}" alt="" class="w-full rounded-xl border border-gray-100 dark:border-white/[0.06] object-cover">
            @else
                <div class="w-full aspect-[4/3] rounded-xl grid place-items-center text-4xl font-black text-white" style="background:{{ $card['accent'] }}">{{ strtoupper(substr($card['name'], 0, 1)) }}</div>
            @endif
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $card['name'] }} <span class="font-normal text-gray-400">by {{ $card['author'] }}</span></p>
                <p class="text-xs text-gray-400 mt-1 line-clamp-3">{{ $card['description'] }}</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-3">{{ $card['priceLabel'] }}</p>
                <ul class="text-[11px] text-gray-400 mt-2 space-y-0.5">
                    <li>✓ One-time purchase — no subscription</li>
                    <li>✓ Yours to use on any of your sites</li>
                    <li>✓ Secure card payment via Stripe</li>
                </ul>
            </div>
        </div>

        @if ($errorMessage)<p class="mt-4 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm font-semibold text-rose-600">{{ $errorMessage }}</p>@endif

        <div class="mt-5 rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
            <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Add it to</label>
            <select wire:model="siteId" class="w-full sm:w-72 border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                @foreach ($this->mySites as $s)<option value="{{ $s['id'] }}">{{ $s['name'] }}</option>@endforeach
            </select>
            <button wire:click="buyNow" wire:loading.attr="disabled"
                    class="block w-full sm:w-auto mt-4 px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
                <span wire:loading.remove wire:target="buyNow">Pay {{ $card['priceLabel'] }} securely →</span>
                <span wire:loading wire:target="buyNow">Taking you to Stripe…</span>
            </button>
            <p class="text-[11px] text-gray-400 mt-2">You'll enter card details on Stripe's secure checkout, then land back in My Designs.</p>
        </div>
    </div>
</div>
