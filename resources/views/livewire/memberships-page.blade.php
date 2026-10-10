@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $btnPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-bold shadow-sm';
    $statusClass = fn ($s) => match ($s) {
        'active' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300',
        'past_due' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300',
        default => 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400',
    };
    $statusLabel = fn ($s) => ucfirst(str_replace('_', ' ', $s));
    $filters = ['all' => ['All', $stats['total']]];
    foreach (['active', 'pending', 'past_due', 'cancelled'] as $s) {
        $filters[$s] = [$statusLabel($s), (int) ($stats['byStatus'][$s] ?? 0)];
    }
    $tierColors = ['#8b5cf6', '#0ea5e9', '#f97316', '#10b981', '#e11d48', '#eab308', '#64748b'];
    $tierColor = fn ($i) => $tierColors[$i % count($tierColors)];
    $tierIndex = $tiers->pluck('id')->flip();
    $typeLabel = ['post' => 'Post', 'page' => 'Page', 'collection' => 'Collection'];
    $needsPaymentSetup = $stats['paidTiers'] > 0 && ! $paymentsReady;
    $initials = fn ($name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
@endphp
<x-tri-layout title="Members" subtitle="Membership tiers, the people who joined, and what only members can see." :site-name="$site->name"
    :labels="['📊 Overview', '👥 Members', '⚡ Quick access']">

    {{-- ── LEFT rail: membership at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="number_format($stats['active'])" label="Active members" :sub="$stats['total'].' on the list in total'"
                icon="M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        <x-tile accent="lime" :value="$stats['newMonth']" label="New this month" :sub="now()->format('F')"
                icon="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        <x-tile accent="lavender" :value="$stats['mrr']" label="MRR" :sub="$stats['mrrOther'] ? '+ '.implode(' + ', $stats['mrrOther']) : 'monthly recurring revenue'"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.1 0 2.1.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.1 0-2.1-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="{{ $stats['churnMonth'] ? 'rose' : 'sky' }}" :value="$stats['churnMonth']" label="Churn this month" :sub="$stats['churnRate'].'% of members at the start of the month'"
                icon="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
        <x-tile accent="{{ $stats['pastDue'] ? 'rose' : 'cocoa' }}" :value="$stats['pastDue']" label="Past due" :sub="$stats['pastDue'] ? 'payment failed — Stripe is retrying' : 'every payment is up to date'"
                icon="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
        <x-tile accent="sky" :value="$stats['free'].' / '.$stats['paid']" label="Free / paid" sub="active members by tier price"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5" x-data="{ tab: $wire.entangle('tab').live }">

    @if ($flash)
        <div class="rounded-2xl px-4 py-3 text-sm bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 flex items-center justify-between gap-3" wire:key="flash-{{ md5($flash) }}">
            <span>{{ $flash }}</span>
            <button type="button" wire:click="$set('flash', '')" class="text-emerald-700/70 dark:text-emerald-300/70 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    @if ($needsPaymentSetup)
        <div class="rounded-2xl px-4 py-3 text-sm bg-amber-50 dark:bg-amber-500/10 text-amber-900 dark:text-amber-200">
            <b>Paid tiers can't take sign-ups yet.</b> Connect Stripe so visitors can pay monthly or yearly — free tiers keep working meanwhile.
            <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="font-bold underline">Set up payments →</a>
        </div>
    @endif

    <x-pill-tabs :tabs="['members' => 'Members', 'tiers' => 'Tiers', 'content' => 'Members-only content']" :dots="$needsPaymentSetup ? ['tiers'] : []" class="!mb-0" />

    {{-- ═════════════ MEMBERS ═════════════ --}}
    <section x-show="tab === 'members'" class="space-y-5">
        @if ($selected)
            {{-- ── Member detail panel ── --}}
            <div class="{{ $panel }} p-5 sm:p-6 space-y-5" wire:key="member-{{ $selected->id }}">
                <div class="flex items-start gap-3">
                    <button type="button" wire:click="closeMember" class="{{ $btnSolid }} text-[13px] px-3 py-1.5">← All members</button>
                </div>
                <div class="flex flex-wrap items-start gap-4">
                    <span class="w-14 h-14 rounded-2xl grid place-items-center text-lg font-extrabold shrink-0" style="background:color-mix(in srgb, var(--primary) 16%, transparent);color:var(--primary)">{{ $initials($selected->name) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xl font-extrabold text-gray-900 dark:text-white truncate">{{ $selected->name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $selected->email }}</p>
                        <p class="mt-2 flex flex-wrap gap-1.5">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusClass($selected->status) }}">{{ $statusLabel($selected->status) }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $selected->tier?->name ?? 'No tier' }} · {{ $selected->priceLabel() }}</span>
                            @if ($selected->cancel_at_period_end)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300">Cancels {{ $selected->renews_at?->format('j M Y') }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-[13px]">
                    @foreach ([
                        'Joined' => $selected->joined_at?->format('j M Y') ?? '—',
                        'Renews' => $selected->renews_at?->format('j M Y') ?? ($selected->isPaid() ? '—' : 'No renewal'),
                        'Last sign-in' => $selected->last_login_at?->diffForHumans() ?? 'Never',
                        'Billing' => $selected->stripe_subscription_id ? 'Stripe subscription' : ($selected->isPaid() ? 'Awaiting payment' : 'Not billed'),
                    ] as $k => $v)
                        <div class="rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04]">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $k }}</dt>
                            <dd class="mt-0.5 font-bold text-gray-900 dark:text-white">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($this->canManage)
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1.5">Change tier</label>
                        <div class="flex gap-2">
                            <select wire:model="changeTierId" class="bkf-input flex-1 text-[13px]">
                                @foreach ($tiers as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} — {{ $t->priceLabel() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="changeTier" class="{{ $btnSolid }} text-[13px] px-3.5 py-2">Change</button>
                        </div>
                        @if ($selected->stripe_subscription_id)
                            <p class="mt-1 text-[11px] text-gray-400">Changes what they can see; their Stripe billing stays as it is.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1.5">Notes <span class="font-normal text-gray-400">(team only)</span></label>
                        <textarea wire:model="noteDraft" rows="3" class="bkf-input w-full text-[13px]" placeholder="Anything the team should know…"></textarea>
                        <button type="button" wire:click="saveNotes" class="{{ $btnSolid }} text-[12px] px-3 py-1.5 mt-1.5">Save notes</button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    @if (in_array($selected->status, ['active', 'past_due'], true))
                        <button type="button" wire:click="resendLink('{{ $selected->id }}')" class="{{ $btnSolid }} text-[13px] px-3.5 py-2">Resend sign-in link</button>
                    @endif
                    @if ($selected->status !== 'cancelled' && ! $selected->cancel_at_period_end)
                        <button type="button" wire:click="cancelMember('{{ $selected->id }}')"
                                data-confirm="Cancel {{ $selected->name }}’s membership?{{ $selected->stripe_subscription_id ? ' Their Stripe subscription stops renewing and access ends on '.($selected->renews_at?->format('j M Y') ?? 'the period end').'.' : '' }}"
                                class="{{ $btnSolid }} text-[13px] px-3.5 py-2 !text-rose-600">Cancel membership</button>
                    @endif
                </div>
                @endif

                <div>
                    <p class="text-[13px] font-extrabold text-gray-900 dark:text-white mb-2">History</p>
                    @forelse ($history as $e)
                        <div class="flex items-start gap-3 py-2 border-t border-gray-100 dark:border-white/[0.05] text-[13px]">
                            <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background:var(--primary)"></span>
                            <span class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $e->label() }}</span>
                                @if ($e->detail)<span class="text-gray-500 dark:text-gray-400"> · {{ $e->detail }}</span>@endif
                            </span>
                            <span class="text-[11px] text-gray-400 shrink-0">{{ $e->created_at?->format('j M Y, H:i') }}</span>
                        </div>
                    @empty
                        <p class="text-[13px] text-gray-400">No history yet.</p>
                    @endforelse
                </div>
            </div>
        @else
            {{-- ── Toolbar: search · status · sort · layout · add · export ── --}}
            <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 min-w-[12rem]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search by name or email…" class="w-full" style="padding-left:2.25rem" />
                    </div>
                    <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                        <option value="recent">Newest first</option>
                        <option value="name">Name A–Z</option>
                        <option value="renewal">Renewing soonest</option>
                    </select>
                    <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                    <button type="button" wire:click="exportCsv" class="{{ $btnSolid }} text-sm px-3.5 py-2.5" title="Download every member as CSV">Export CSV</button>
                    @if ($this->canManage && $tiers->isNotEmpty())
                        <button type="button" wire:click="$toggle('showAdd')" class="{{ $btnPrimary }} text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add member
                        </button>
                    @endif
                </div>
                <div class="flex gap-2 overflow-x-auto no-scrollbar">
                    @foreach ($filters as $key => [$label, $n])
                        <button type="button" wire:click="setStatus('{{ $key }}')"
                            class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                                {{ $statusFilter === $key
                                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                    : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                            {{ $label }} <span class="opacity-60">{{ $n }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($showAdd && $this->canManage)
                <form wire:submit="addMember" class="{{ $panel }} !rounded-2xl p-5 space-y-3">
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Add a member</p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <x-field.text wire:model="addName" placeholder="Name" class="w-full" />
                            @error('addName')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-field.text wire:model="addEmail" type="email" placeholder="Email" class="w-full" />
                            @error('addEmail')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <select wire:model="addTierId" class="bkf-input w-full text-[13px]">
                                <option value="">Choose a tier…</option>
                                @foreach ($tiers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                            </select>
                            @error('addTierId')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400">Added members are active straight away and complimentary — nobody is charged. They get a welcome email with a sign-in link.</p>
                    <div class="flex gap-2">
                        <button class="{{ $btnPrimary }} text-sm px-4 py-2" style="background:var(--primary);color:var(--on-primary)">Add member</button>
                        <button type="button" wire:click="$set('showAdd', false)" class="{{ $btnSolid }} text-sm px-4 py-2">Cancel</button>
                    </div>
                </form>
            @endif

            @if ($members->isEmpty())
                <div class="{{ $panel }} px-6 py-16 text-center">
                    <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                        <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    @if ($stats['total'] === 0)
                        <p class="text-[15px] font-bold text-gray-900 dark:text-white">No members yet</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                            {{ $tiers->isEmpty() ? 'Create a tier first — free, or paid monthly / yearly — then visitors can join from your site.' : 'Visitors join from your site’s membership block. You can also add people yourself.' }}
                        </p>
                        @if ($tiers->isEmpty() && $this->canManage)
                            <button type="button" x-on:click="tab = 'tiers'; $wire.newTier()" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create the first tier</button>
                        @endif
                    @else
                        <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                        <button type="button" x-on:click="$wire.set('search', ''); $wire.setStatus('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all members</button>
                    @endif
                </div>
            @elseif ($viewMode === 'grid')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($members as $m)
                        <button type="button" wire:click="selectMember('{{ $m->id }}')" wire:key="mc-{{ $m->id }}"
                                class="text-left {{ $panel }} !rounded-2xl p-5 hover:shadow-md transition-shadow">
                            <span class="flex items-start gap-3">
                                <span class="w-11 h-11 rounded-xl grid place-items-center text-sm font-extrabold shrink-0 text-white" style="background:{{ $m->tier_id && isset($tierIndex[$m->tier_id]) ? $tierColor($tierIndex[$m->tier_id]) : '#94a3b8' }}">{{ $initials($m->name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $m->name }}</span>
                                    <span class="block text-[12px] text-gray-400 truncate">{{ $m->email }}</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 {{ $statusClass($m->status) }}">{{ $statusLabel($m->status) }}</span>
                            </span>
                            <span class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-gray-500 dark:text-gray-400">
                                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $m->tier?->name ?? 'No tier' }}</span>
                                <span>{{ $m->priceLabel() }}</span>
                                @if ($m->renews_at && $m->status !== 'cancelled')<span>{{ $m->cancel_at_period_end ? 'ends' : 'renews' }} {{ $m->renews_at->format('j M') }}</span>@endif
                                <span class="ml-auto text-gray-400">{{ ($m->joined_at ?? $m->created_at)->diffForHumans() }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @else
                @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
                <div class="{{ $panel }} !rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="px-4 py-3">Member</th>
                                    <th class="px-4 py-3">Tier</th>
                                    <th class="px-4 py-3">Status</th>
                                    @unless ($compact)<th class="px-4 py-3">Renews</th><th class="px-4 py-3">Joined</th>@endunless
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                                @foreach ($members as $m)
                                    <tr wire:key="mr-{{ $m->id }}" wire:click="selectMember('{{ $m->id }}')" class="cursor-pointer hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                        <td class="{{ $pad }}">
                                            <span class="block font-semibold text-gray-900 dark:text-white truncate">{{ $m->name }}</span>
                                            @unless ($compact)<span class="block text-[11px] text-gray-400 truncate">{{ $m->email }}</span>@endunless
                                        </td>
                                        <td class="{{ $pad }} text-[12.5px] text-gray-600 dark:text-gray-300">{{ $m->tier?->name ?? '—' }} <span class="text-gray-400">· {{ $m->priceLabel() }}</span></td>
                                        <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusClass($m->status) }}">{{ $statusLabel($m->status) }}</span></td>
                                        @unless ($compact)
                                            <td class="{{ $pad }} text-[12px] text-gray-500 whitespace-nowrap">{{ $m->renews_at?->format('j M Y') ?? '—' }}</td>
                                            <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $m->joined_at?->format('j M Y') ?? '—' }}</td>
                                        @endunless
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </section>

    {{-- ═════════════ TIERS ═════════════ --}}
    <section x-show="tab === 'tiers'" x-cloak class="space-y-4">
        @if ($showTierForm && $this->canManage)
            <form wire:submit="saveTier" class="{{ $panel }} p-5 sm:p-6 space-y-4">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $tierId ? 'Edit tier' : 'New tier' }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1">Name</label>
                        <x-field.text wire:model="tName" placeholder="e.g. Supporter" class="w-full" />
                        @error('tName')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1">Price ({{ strtoupper($currency) }}) — 0 for free</label>
                        <x-field.text wire:model="tPrice" type="number" step="0.01" min="0" class="w-full" />
                        @error('tPrice')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1">Billed</label>
                        <select wire:model="tInterval" class="bkf-input w-full text-[13px]">
                            <option value="month">Monthly</option>
                            <option value="year">Yearly</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1">Description</label>
                        <textarea wire:model="tDescription" rows="2" class="bkf-input w-full text-[13px]"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1">Benefits <span class="font-normal text-gray-400">(one per line)</span></label>
                        <textarea wire:model="tBenefits" rows="4" class="bkf-input w-full text-[13px]" placeholder="Members-only posts&#10;Monthly newsletter"></textarea>
                    </div>
                    <label class="sm:col-span-2 inline-flex items-center gap-2 text-[13px] text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="tActive" class="rounded"> Open for new members
                    </label>
                </div>
                @if ((float) $tPrice > 0 && ! $paymentsReady)
                    <p class="rounded-xl px-3 py-2 text-[12px] bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200">
                        Paid tiers need payments connected before anyone can join. <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="font-bold underline">Set up payments</a>
                    </p>
                @endif
                @if ($tierId)
                    <p class="text-[11px] text-gray-400">Price changes apply to new members; existing subscribers keep the price they joined at.</p>
                @endif
                <div class="flex gap-2">
                    <button class="{{ $btnPrimary }} text-sm px-4 py-2" style="background:var(--primary);color:var(--on-primary)">Save tier</button>
                    <button type="button" wire:click="cancelTierForm" class="{{ $btnSolid }} text-sm px-4 py-2">Cancel</button>
                </div>
            </form>
        @elseif ($this->canManage)
            <div class="flex justify-end">
                <button type="button" wire:click="newTier" class="{{ $btnPrimary }} text-sm px-4 py-2.5" style="background:var(--primary);color:var(--on-primary)">＋ New tier</button>
            </div>
        @endif

        @forelse ($tiers as $i => $t)
            @php $n = (int) ($stats['byTier']->firstWhere('tier.id', $t->id)['n'] ?? 0); @endphp
            <div class="{{ $panel }} !rounded-2xl p-5 flex flex-wrap items-start gap-4 {{ $t->active ? '' : 'opacity-60' }}" wire:key="tier-{{ $t->id }}">
                <span class="w-3 self-stretch rounded-full shrink-0" style="background:{{ $tierColor($i) }}"></span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $t->name }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $t->isFree() ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300' }}">{{ $t->priceLabel() }}</span>
                        @unless ($t->active)<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500">Hidden</span>@endunless
                        @if (! $t->isFree() && ! $paymentsReady)<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300">Needs payments</span>@endif
                    </p>
                    @if ($t->description)<p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">{{ $t->description }}</p>@endif
                    @if ($t->benefits)
                        <ul class="mt-2 space-y-0.5 text-[12.5px] text-gray-600 dark:text-gray-300">
                            @foreach ($t->benefits as $b)<li>✓ {{ $b }}</li>@endforeach
                        </ul>
                    @endif
                    <p class="mt-2 text-[12px] text-gray-400">{{ $n }} active {{ Str::plural('member', $n) }} · slug <code>{{ $t->slug }}</code></p>
                </div>
                @if ($this->canManage)
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" wire:click="moveTier('{{ $t->id }}', -1)" @disabled($loop->first) class="{{ $btnSolid }} w-8 h-8 disabled:opacity-30" title="Move up">↑</button>
                        <button type="button" wire:click="moveTier('{{ $t->id }}', 1)" @disabled($loop->last) class="{{ $btnSolid }} w-8 h-8 disabled:opacity-30" title="Move down">↓</button>
                        <button type="button" wire:click="editTier('{{ $t->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Edit</button>
                        <button type="button" wire:click="toggleTier('{{ $t->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">{{ $t->active ? 'Hide' : 'Show' }}</button>
                        <button type="button" wire:click="deleteTier('{{ $t->id }}')" data-confirm="Delete the “{{ $t->name }}” tier?{{ $n ? ' It has members, so it will be hidden instead.' : '' }}" class="{{ $btnSolid }} text-[12px] px-3 py-1.5 !text-rose-600">Delete</button>
                    </div>
                @endif
            </div>
        @empty
            @unless ($showTierForm)
                <div class="{{ $panel }} px-6 py-12 text-center">
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No tiers yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Start with a free tier for sign-ups, then add paid monthly or yearly tiers billed through your Stripe.</p>
                </div>
            @endunless
        @endforelse
    </section>

    {{-- ═════════════ MEMBERS-ONLY CONTENT ═════════════ --}}
    <section x-show="tab === 'content'" x-cloak class="space-y-4">
        @if ($this->canManage)
            <form wire:submit="gateContent" class="{{ $panel }} p-5 space-y-3">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Make something members-only</p>
                <div class="grid sm:grid-cols-[10rem_1fr] gap-3">
                    <select wire:model.live="cType" class="bkf-input w-full text-[13px]">
                        <option value="post">Post</option>
                        <option value="page">Page</option>
                        <option value="collection">Collection</option>
                    </select>
                    <div>
                        <select wire:model="cId" class="bkf-input w-full text-[13px]">
                            <option value="">Choose a {{ strtolower($typeLabel[$cType] ?? 'item') }}…</option>
                            @foreach ($contentOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('cId')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                @if ($tiers->isNotEmpty())
                    <div>
                        <p class="text-[12px] font-bold text-gray-700 dark:text-gray-200 mb-1.5">Who can see it <span class="font-normal text-gray-400">(none ticked = every member)</span></p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($tiers as $t)
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 cursor-pointer">
                                    <input type="checkbox" wire:model="cTiers" value="{{ $t->id }}" class="rounded"> {{ $t->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <button class="{{ $btnPrimary }} text-sm px-4 py-2" style="background:var(--primary);color:var(--on-primary)">Make members-only</button>
            </form>
        @endif

        @if ($rules->isEmpty())
            <div class="{{ $panel }} px-6 py-12 text-center">
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Everything is public</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Pick posts, pages or collections above to lock them to members — your template shows a “members only” prompt in their place.</p>
            </div>
        @else
            <div class="{{ $panel }} !rounded-2xl divide-y divide-gray-100 dark:divide-white/[0.05]">
                @foreach ($rules as $r)
                    @php $title = $titles[$r->content_type][$r->content_id] ?? null; @endphp
                    <div class="p-4 flex flex-wrap items-center gap-3" wire:key="rule-{{ $r->id }}">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $typeLabel[$r->content_type] ?? $r->content_type }}</span>
                        <span class="flex-1 min-w-0 text-[13.5px] font-semibold truncate {{ $title ? 'text-gray-900 dark:text-white' : 'text-gray-400 line-through' }}">{{ $title ?? 'Deleted item' }}</span>
                        <span class="flex flex-wrap gap-1.5">
                            @foreach ($tiers as $t)
                                @php $on = in_array($t->id, $r->tierIds(), true); @endphp
                                <button type="button" @if ($this->canManage) wire:click="toggleRuleTier('{{ $r->id }}', '{{ $t->id }}')" @else disabled @endif
                                        class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold border {{ $on ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white dark:bg-[#1d1e2a] text-gray-500 dark:text-gray-400 border-gray-200 dark:border-white/[0.1]' }}">{{ $t->name }}</button>
                            @endforeach
                            @if ($r->tierIds() === [])<span class="text-[11.5px] text-gray-400 self-center">every member</span>@endif
                        </span>
                        @if ($this->canManage)
                            <button type="button" wire:click="ungate('{{ $r->id }}')" data-confirm="Make “{{ $title ?? 'this item' }}” public again?" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Make public</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · recent joins · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Membership summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $stats['active'] + $stats['pastDue'] }}</b> with access ·
                <b class="text-gray-900 dark:text-white">{{ $stats['mrr'] }}</b> MRR
            </p>
            @php $withAccess = max(1, $stats['byTier']->sum('n')); @endphp
            @if ($stats['byTier']->isNotEmpty())
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($stats['byTier'] as $row)
                        <span style="width:{{ round($row['n'] / $withAccess * 100, 2) }}%;background:{{ $tierColor($tierIndex[$row['tier']->id] ?? 0) }}" title="{{ $row['tier']->name }} · {{ $row['n'] }}"></span>
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach ($stats['byTier'] as $row)
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $tierColor($tierIndex[$row['tier']->id] ?? 0) }}"></span>
                            <span class="text-gray-600 dark:text-gray-300 truncate">{{ $row['tier']->name }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $row['n'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-[12px] text-gray-400">No members with access yet.</p>
            @endif
        </div>

        @if ($needsPaymentSetup || $stats['pastDueList']->isNotEmpty() || $stats['stalePending'])
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($needsPaymentSetup)
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">Payments not connected</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">{{ $stats['paidTiers'] }} paid {{ Str::plural('tier', $stats['paidTiers']) }} can't take sign-ups. Connect Stripe →</p>
                    </a>
                @endif
                @foreach ($stats['pastDueList'] as $m)
                    <button type="button" x-on:click="$wire.set('tab', 'members'); $wire.selectMember('{{ $m->id }}')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200 truncate">{{ $m->name }} — payment failed</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 truncate">{{ $m->email }}</p>
                    </button>
                @endforeach
                @if ($stats['stalePending'])
                    <button type="button" x-on:click="$wire.set('tab', 'members'); $wire.setStatus('pending')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['stalePending'] }} unfinished {{ Str::plural('checkout', $stats['stalePending']) }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Started joining a paid tier but never paid. Show them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if ($stats['recentJoins']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recent joins</p>
            <div class="space-y-2">
                @foreach ($stats['recentJoins'] as $m)
                    <button type="button" x-on:click="$wire.set('tab', 'members'); $wire.selectMember('{{ $m->id }}')" class="w-full flex items-center gap-2.5 group text-left">
                        <span class="w-7 h-7 rounded-lg grid place-items-center shrink-0 text-[11px] font-extrabold text-white" style="background:{{ $m->tier_id && isset($tierIndex[$m->tier_id]) ? $tierColor($tierIndex[$m->tier_id]) : '#94a3b8' }}">{{ $initials($m->name) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $m->name }}</span>
                            <span class="block text-[11px] text-gray-400 truncate">{{ $m->tier?->name }}</span>
                        </span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $m->joined_at->diffForHumans(null, true) }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Posts', 'Write members-only posts', route('site.posts', $site->name)],
                    ['Pages', 'Gate whole pages', route('pages', $site->name)],
                    ['Collections', 'Members-only lists', route('collections', $site->name)],
                    ['Payments', $paymentsReady ? 'Stripe connected' : 'Connect Stripe', route('site.payments', $site->name)],
                    ['Add-ons', 'Welcome message & currency', route('site.marketplace', $site->name)],
                    ['Member page', 'What members see', route('memberships.public.manage', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" @if ($label !== 'Member page') wire:navigate @else target="_blank" rel="noopener" @endif class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
