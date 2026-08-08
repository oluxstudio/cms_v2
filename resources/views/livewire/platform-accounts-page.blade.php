@php use App\Support\Money; @endphp
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Client accounts</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Subscriptions, custom per-client pricing and plan assignment.</p>
        </div>
        <div class="relative w-full sm:w-auto">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search accounts…"
                   class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-full sm:w-64">
        </div>
    </div>

    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        @forelse($accounts as $account)
        @php $sub = $account->currentSubscription(); $tier = $sub->tier(); @endphp
        <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $account->name }}
                    @if($account->isSuper())<span class="ml-1 text-[9px] font-bold uppercase text-indigo-400">admin</span>@endif
                </p>
                <p class="text-xs text-gray-400 truncate">{{ $account->email }} · {{ $account->sites_count }} {{ Str::plural('site', $account->sites_count) }}</p>
            </div>

            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold text-white" style="background: {{ $tier['color'] }}">
                {{ $sub->badgeLabel() }}</span>
            @if($sub->price_overrides)
                <span class="text-[10px] font-bold text-emerald-500" title="Has custom pricing">★ custom</span>
            @endif

            <select wire:change="assignPlan('{{ $account->id }}', $event.target.value)"
                    class="text-xs font-semibold pr-7 pl-3 py-1.5 rounded-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.05] text-gray-600 dark:text-gray-300 cursor-pointer focus:outline-none">
                <option value="">Set plan…</option>
                @foreach(config('plans.tiers') as $key => $t)
                    <option value="{{ $key }}" @selected($sub->plan === $key)>{{ $t['name'] }}</option>
                @endforeach
            </select>

            <button wire:click="edit('{{ $account->id }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                Pricing
            </button>
        </div>
        @empty
        <p class="px-5 py-14 text-center text-sm text-gray-400">No accounts match.</p>
        @endforelse
        @if($accounts->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 dark:border-white/[0.05]">{{ $accounts->links() }}</div>
        @endif
    </div>

    {{-- ── Per-client pricing drawer ── --}}
    @if($editingId)
    @php $u = \App\Models\User::find($editingId); @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="close"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-md p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Custom pricing — {{ $u?->name }}</h2>
            <p class="text-xs text-gray-400 mt-1 mb-5">Monthly price per tier for THIS client. Blank = list price. They'll see it as “★ Your price”.</p>
            <form wire:submit="savePrices" class="space-y-3">
                @foreach(config('plans.tiers') as $key => $t)
                    @continue($key === 'trial')
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-xs font-bold" style="color: {{ $t['color'] }}">{{ $t['name'] }}</span>
                        <span class="text-xs text-gray-400 tabular-nums w-16">{{ Money::format($t['price_cents'], 'gbp') }}</span>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">£</span>
                            <input wire:model="prices.{{ $key }}" type="text" inputmode="decimal" placeholder="list price"
                                   class="w-full pl-7 pr-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" wire:click="close" class="px-4 py-2 rounded-xl text-sm font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save pricing</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
