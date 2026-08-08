<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">API Keys</h1>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">
            Bearer tokens scoped to <span class="font-semibold text-gray-600 dark:text-gray-300">{{ ucwords(str_replace('-', ' ', $site->name)) }}</span> —
            for client sites, static builds and MCP agents. Each key can only touch this site.
        </p>
    </div>

    @if ($successMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>
    @endif

    {{-- Freshly minted token — shown once --}}
    @if ($generatedToken)
    <div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <p class="text-xs font-semibold text-amber-800 mb-2">⚠️ Copy this key now — it will not be shown again.</p>
        <code class="text-xs font-mono text-amber-900 bg-amber-100 px-3 py-2 rounded-lg block break-all">{{ $generatedToken }}</code>
        <p class="text-[11px] text-amber-700 mt-2">Use it as <code class="font-mono">Authorization: Bearer {{ Str::limit($generatedToken, 12, '…') }}</code></p>
    </div>
    @endif

    {{-- Generate --}}
    <div class="mb-6 p-5 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm space-y-3">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Create a key for this site</h3>
        <div class="flex flex-wrap gap-2">
            <div class="flex-1 min-w-[180px]">
                <input wire:model="newName" type="text" placeholder="Key name (e.g. Website build, Blog agent)"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            </div>
            <select wire:model="newExpiry" class="px-3 py-2 pr-7 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                <option value="">Never expires</option>
                <option value="30">Expires in 30 days</option>
                <option value="90">Expires in 90 days</option>
                <option value="365">Expires in 1 year</option>
            </select>
        </div>
        <details class="text-xs">
            <summary class="cursor-pointer text-gray-500 dark:text-gray-400 font-semibold">Limit abilities <span class="font-normal text-gray-400">— none ticked = everything you can do on this site</span></summary>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mt-2">
                @foreach ($this->abilityOptions as $perm)
                    <label class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model="newAbilities" value="{{ $perm }}" class="rounded border-gray-300"> {{ $perm }}
                    </label>
                @endforeach
            </div>
        </details>
        <button wire:click="generate"
                class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
            <span wire:loading.remove wire:target="generate">Generate key</span>
            <span wire:loading wire:target="generate">…</span>
        </button>
        @error('newName') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    {{-- Existing keys --}}
    <div class="p-5 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Active keys for this site</h3>
        @forelse ($this->tokens as $token)
        <div class="flex items-center justify-between py-3 px-4 bg-gray-50 dark:bg-white/[0.03] rounded-xl mb-2">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $token->name }}
                    @if ($token->abilities)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300 ml-1">{{ count($token->abilities) }} {{ Str::plural('ability', count($token->abilities)) }}</span>@else<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-600 ml-1">full access</span>@endif
                    @if ($token->isExpired())<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 ml-1">expired</span>
                    @elseif ($token->expires_at)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 ml-1">expires {{ $token->expires_at->diffForHumans() }}</span>@endif
                </p>
                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $token->maskedToken() }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                    By {{ $token->user->name ?? '—' }} · created {{ $token->created_at->diffForHumans() }}{{ $token->last_used_at ? ' · last used '.$token->last_used_at->diffForHumans() : ' · never used' }}
                </p>
            </div>
            <button wire:click="revoke('{{ $token->id }}')" data-confirm="Revoke this API key? Anything using it stops working immediately."
                    class="text-xs font-medium text-red-500 hover:text-red-700 px-3 py-1.5 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors shrink-0">
                Revoke
            </button>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-6">No API keys for this site yet.</p>
        @endforelse
    </div>

</div>
