<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Go live</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Put this site on your own domain — served by the platform, always showing your latest content.</p>
        </div>
        @if ($site->live)
            <span class="flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 text-emerald-500 text-sm font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> LIVE
            </span>
        @else
            <span class="px-3.5 py-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 text-sm font-bold">Offline</span>
        @endif
    </div>

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- ── Step 1 — domain ── --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">1 · Your domain</h2>
        <p class="text-xs text-gray-400 mb-3">The address visitors will use. Enter it without www — both work once live.</p>
        <form wire:submit="saveDomain" class="flex flex-wrap items-center gap-3">
            <input wire:model="domain" type="text" placeholder="example.com" required
                   class="flex-1 min-w-[220px] px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save domain</button>
        </form>
    </div>

    {{-- ── Step 2 — DNS ── --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5 mb-4">
        <div class="flex items-center justify-between gap-3 mb-1">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">2 · Point your DNS here</h2>
            @if ($site->domain_verified_at)
                <span class="text-[11px] font-bold text-emerald-500">✓ verified {{ $site->domain_verified_at->diffForHumans() }}</span>
            @endif
        </div>
        <p class="text-xs text-gray-400 mb-3">Create these records with your domain registrar, then verify. Propagation can take minutes to 24 hours.</p>

        @if ($dnsTarget === '')
            <p class="px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-sm text-amber-600 dark:text-amber-400 mb-3">
                Platform not configured: set <span class="font-mono font-bold">PLATFORM_DNS_TARGET</span> (your server's public IP) in the platform's .env to enable DNS instructions and verification.
            </p>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-white/[0.06] mb-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                            <th class="px-4 py-2.5">Type</th><th class="px-4 py-2.5">Host</th><th class="px-4 py-2.5">Value</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-700 dark:text-gray-200">
                        <tr class="border-b border-gray-50 dark:border-white/[0.04]">
                            <td class="px-4 py-2.5">{{ filter_var($dnsTarget, FILTER_VALIDATE_IP) ? 'A' : 'CNAME' }}</td>
                            <td class="px-4 py-2.5">@</td>
                            <td class="px-4 py-2.5">{{ $dnsTarget }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5">CNAME</td>
                            <td class="px-4 py-2.5">www</td>
                            <td class="px-4 py-2.5">{{ $site->domain ?: 'your-domain.com' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button wire:click="verifyDns" @disabled(! $site->domain || $dnsTarget === '')
                    class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-700 dark:text-gray-200 hover:border-indigo-400 hover:text-indigo-600 transition-colors disabled:opacity-40">
                <span wire:loading.remove wire:target="verifyDns">Verify DNS</span>
                <span wire:loading wire:target="verifyDns">Checking…</span>
            </button>
            @if (is_array($dnsFound) && ! $site->domain_verified_at)
                <span class="text-xs text-gray-400">Found: {{ $dnsFound ? implode(', ', $dnsFound) : 'no records yet' }}</span>
            @endif
        </div>
    </div>

    {{-- ── Step 3 — go live ── --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">3 · Switch it on</h2>
        <p class="text-xs text-gray-400 mb-4">
            Serving uses the <span class="font-semibold">{{ $site->renderTemplateKey() }}</span> renderer with content loaded live from this CMS — publish once, edit forever.
            @unless ($hasBuild) <span class="text-amber-500 font-semibold">No renderer build found yet — visitors will see a holding page until one is built.</span> @endunless
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="toggleLive"
                    class="px-6 py-3 rounded-xl text-sm font-bold text-white {{ $site->live ? 'bg-gray-500 hover:bg-gray-600' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                {{ $site->live ? 'Take offline' : 'Go live' }}
            </button>
            @if ($site->live && $site->domain)
                <a href="http://{{ $site->domain }}" target="_blank" rel="noopener"
                   class="text-sm font-semibold text-indigo-500 hover:text-indigo-600">Visit {{ $site->domain }} ↗</a>
            @endif
            @if (! $site->domain_verified_at && $site->domain)
                <span class="text-xs text-amber-500">DNS not verified yet — the domain only works once it points at the platform.</span>
            @endif
        </div>
    </div>
</div>
