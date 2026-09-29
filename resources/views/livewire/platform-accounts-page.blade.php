@php
    use App\Support\Money;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $planTotal = max(1, $summary['plans']->sum());
@endphp
<x-tri-layout title="Client accounts" subtitle="Subscriptions, custom per-client pricing and plan assignment."
    :labels="['📊 Numbers', '👥 Accounts', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    {{-- ══ LEFT rail: numbers ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="number_format($summary['accounts'])" label="Accounts" :sub="'+'.$summary['new_month'].' this month'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-6.9M15 7a4 4 0 11-8 0 4 4 0 018 0z" />
        <x-tile accent="lime" :value="$summary['paying']" label="Paying" sub="active plans"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$summary['trialing']" label="On trial" sub="not paying yet"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$summary['custom']" label="Custom pricing" sub="per-client prices"
                icon="M11.48 3.5a.562.562 0 011.04 0l2.125 5.11a.563.563 0 00.475.345l5.518.442c.5.04.7.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557L3.04 10.385a.562.562 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
    </div>
    </x-slot:rail>

<div class="max-w-[52rem] mx-auto">
    <div class="mb-3">
        <x-field.search model="search" placeholder="Search accounts by name or email…" />
    </div>

    {{-- Sort + plan filter --}}
    <div class="flex flex-wrap items-center gap-1.5 mb-4 -mt-2">
        <select wire:change="setSort($event.target.value)"
                class="text-xs font-semibold pr-7 pl-3 py-1.5 rounded-full border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.05] text-gray-600 dark:text-gray-300 cursor-pointer focus:outline-none">
            @foreach(['name' => 'Sort: name', 'newest' => 'Sort: newest', 'storage' => 'Sort: storage', 'visits' => 'Sort: visits 30d'] as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @foreach(config('plans.tiers') as $key => $t)
            <button wire:click="filterPlan('{{ $key }}')"
                    class="px-3 py-1.5 rounded-full text-[11px] font-bold border transition-colors
                           {{ $planFilter === $key ? 'text-white border-transparent' : 'text-gray-500 border-gray-200 dark:border-white/[0.08]' }}"
                    @if($planFilter === $key) style="background: {{ $t['color'] }}" @endif>{{ $t['name'] }}</button>
        @endforeach
    </div>

    <div class="{{ $panel }} overflow-hidden">
        @forelse($accounts as $account)
        @php $sub = $account->currentSubscription(); $tier = $sub->tier(); @endphp
        <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-white/[0.04] last:border-0">
            <div class="min-w-0 flex-1">
                <a href="{{ route('admin.account', $account->id) }}" wire:navigate
                   class="block text-[14px] font-bold text-gray-900 dark:text-white truncate hover:underline">{{ $account->name }}
                    @if($account->isSuper())<span class="ml-1 text-[9px] font-bold uppercase" style="color:var(--primary)">admin</span>@endif
                </a>
                <p class="text-xs text-gray-400 truncate">{{ $account->email }} · {{ $account->sites_count }} {{ Str::plural('site', $account->sites_count) }}</p>
            </div>

            {{-- Usage: storage vs plan limit + 30d traffic --}}
            @php
                $bytes = (int) ($account->storage_bytes ?? 0);
                $limitBytes = $sub->storageLimitBytes();
                $pct = $limitBytes ? min(100, (int) round($bytes / $limitBytes * 100)) : null;
                $fmt = $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format($bytes / 1024, 1).' KB';
            @endphp
            <div class="w-36 shrink-0 hidden sm:block">
                <p class="text-[10px] text-gray-400 tabular-nums flex justify-between">
                    <span>{{ $fmt }}</span>
                    <span class="{{ ($pct ?? 0) > 85 ? 'text-rose-500 font-bold' : '' }}">{{ $pct !== null ? $pct.'%' : '∞' }}</span>
                </p>
                <span class="block mt-0.5 h-1.5 rounded-full bg-black/[0.05] dark:bg-white/[0.07] overflow-hidden">
                    <span class="block h-full rounded-full " style="width: {{ $pct ?? 4 }}%; background: {{ ($pct ?? 0) > 85 ? '#f43f5e' : 'var(--primary)' }}"></span>
                </span>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ number_format((int) ($account->visits_30d ?? 0)) }} visits · 30d</p>
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
                    class="fx px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">
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
                                   class="bkf-input w-full !pl-7">
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" wire:click="close" class="fx px-4 py-2 rounded-xl text-sm font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">Cancel</button>
                    <button type="submit" class="fx px-5 py-2 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Save pricing</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

    {{-- ══ RIGHT rail: plan mix + related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-3">Plan mix</h3>
            @foreach (config('plans.tiers') as $key => $t)
                @php $n = (int) ($summary['plans'][$key] ?? 0); @endphp
                <button wire:click="filterPlan('{{ $key }}')" class="w-full flex items-center gap-3 py-1.5 text-left">
                    <span class="w-20 text-[12.5px] font-bold shrink-0" style="color: {{ $t['color'] }}">{{ $t['name'] }}</span>
                    <span class="flex-1 h-2.5 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                        <span class="block h-full rounded-full" style="width: {{ round($n / $planTotal * 100) }}%; background: {{ $t['color'] }}"></span>
                    </span>
                    <span class="w-8 text-right text-[12.5px] font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ $n }}</span>
                </button>
            @endforeach
            <p class="mt-2 text-[11.5px] text-gray-500 dark:text-gray-400">Click a plan to filter the list.</p>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['Dashboard', 'platform numbers', route('admin.dashboard')], ['Templates', 'catalog, uploads & review', route('admin.templates')]] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" wire:navigate class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
