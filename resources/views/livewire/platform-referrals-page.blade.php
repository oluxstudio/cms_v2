@php
    $money = fn (int $c, ?string $cur = 'gbp') => \App\Support\Money::format($c, $cur ?: 'gbp');
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $btnPrimary = 'background:var(--primary);color:var(--on-primary)';
    $statusPill = fn (string $s) => match ($s) {
        'collected', 'transferred', 'credited' => ['#dcfce7', '#15803d'],
        'converted', 'billed', 'ready' => ['#e0f2fe', '#0369a1'],
        'disputed', 'pending_consent', 'pending' => ['#fef3c7', '#92400e'],
        'failed', 'void', 'consent_refused', 'declined' => ['#ffe4e6', '#be123c'],
        'shared', 'accepted' => ['#ede9fe', '#6d28d9'],
        default => ['#f3f4f6', '#374151'],
    };
    $label = fn (string $s) => ucfirst(str_replace('_', ' ', $s));
    $siteName = fn ($site) => $site?->name ?? 'Deleted site';
    $tabs = ['disputes' => 'Disputes', 'payouts' => 'Payouts', 'referrals' => 'All referrals', 'members' => 'Members'];
@endphp

<x-tri-layout title="Referrals" subtitle="The Olux Referral Network — disputes, referrer payouts, every lead and every member."
    :labels="['📊 Numbers', '🤝 Referrals', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm overflow-x-auto no-scrollbar max-w-full">
            @foreach ($tabs as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="shrink-0 px-4 py-1.5 rounded-full text-sm font-semibold transition-colors whitespace-nowrap {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>
                    {{ $tl }}
                    @if ($tk === 'disputes' && $stats['disputes'])<span class="ml-1 text-[11px] font-extrabold px-1.5 rounded-full bg-amber-100 text-amber-800">{{ $stats['disputes'] }}</span>@endif
                    @if ($tk === 'payouts' && $stats['failed'])<span class="ml-1 text-[11px] font-extrabold px-1.5 rounded-full bg-rose-100 text-rose-700">{{ $stats['failed'] }}</span>@endif
                </button>
            @endforeach
        </div>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$money($stats['revenue'])" label="Olux revenue" sub="cut on collected fees"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$stats['members']" label="Network members" :sub="$stats['accepting'].' accepting'"
                icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        <x-tile accent="sky" :value="$stats['month']" label="Referrals this month" :sub="$stats['all'].' all time'"
                icon="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
        <x-tile accent="lime" :value="$stats['conversion'].'%'" label="Conversion rate" :sub="$stats['converted'].' converted'" />
        <x-tile accent="cocoa" :value="$money($stats['billed'])" label="Fees billed" sub="billed + collected"
                icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
        <x-tile accent="lime" :value="$money($stats['paid_out'])" label="Paid to referrers" sub="transfers + credit"
                icon="M5 13l4 4L19 7" />
        <x-tile accent="cocoa" :value="$stats['disputes']" label="Open disputes"
                icon="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        <x-tile accent="rose" :value="$stats['failed']" label="Failed payouts" :sub="$stats['ready'].' ready'"
                icon="M6 18L18 6M6 6l12 12" />
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto">
        @if ($tab === 'disputes')
            <div class="space-y-3">
                @forelse ($disputes as $r)
                    <div class="{{ $panel }} p-5" wire:key="dispute-{{ $r->id }}">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-[12px] font-bold text-gray-500">{{ $r->reference }}</span>
                                    <span class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $r->customer_name }}</span>
                                </p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400 break-all">{{ $r->customer_email }}@if ($r->customer_phone) · {{ $r->customer_phone }}@endif</p>
                                <p class="mt-1 text-[12.5px] text-gray-700 dark:text-gray-200">
                                    <span class="font-semibold">{{ $siteName($r->fromSite) }}</span>
                                    <span class="text-gray-400">→</span>
                                    <span class="font-semibold">{{ $siteName($r->toSite) }}</span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-display text-xl font-extrabold text-gray-900 dark:text-white">{{ $money((int) $r->fee_cents, $r->currency) }}</p>
                                <p class="text-[11.5px] text-gray-500 dark:text-gray-400">fee · disputed {{ $r->disputed_at?->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2 @md:grid-cols-2">
                            <div class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">Receiver's reason</p>
                                <p class="mt-0.5 text-[12.5px] text-gray-800 dark:text-gray-100 whitespace-pre-line">{{ $r->dispute_reason ?: '—' }}</p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Converted via</p>
                                <p class="mt-0.5 text-[12.5px] font-mono text-gray-800 dark:text-gray-100">{{ $r->converted_via ?: '—' }}</p>
                                <p class="text-[11.5px] text-gray-500 dark:text-gray-400">{{ $r->converted_at?->format('j M Y H:i') }}</p>
                            </div>
                        </div>

                        @if ($r->events->isNotEmpty())
                            <details class="mt-3">
                                <summary class="cursor-pointer text-[12px] font-bold text-gray-600 dark:text-gray-300">Timeline ({{ $r->events->count() }})</summary>
                                <ol class="mt-2 ml-1 border-l border-gray-200 dark:border-white/[0.1] space-y-1.5">
                                    @foreach ($r->events as $ev)
                                        <li class="pl-3 text-[12px] text-gray-600 dark:text-gray-300">
                                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $label($ev->type) }}</span>
                                            · {{ $ev->created_at?->format('j M Y H:i') }}
                                            @if (! empty($ev->data['note'] ?? $ev->data['reason'] ?? null))
                                                <span class="block text-gray-500 dark:text-gray-400">{{ $ev->data['note'] ?? $ev->data['reason'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <label class="flex-1 min-w-[12rem]">
                                <span class="bkf-label">Note (optional)</span>
                                <input type="text" wire:model="notes.{{ $r->id }}" maxlength="500" class="bkf-input w-full" placeholder="Why you decided this way…">
                            </label>
                            <button type="button" wire:click="resolve('{{ $r->id }}', 'upheld')"
                                    data-confirm="Uphold the dispute on {{ $r->reference }}? The referral is voided and {{ $siteName($r->toSite) }} is not charged."
                                    class="{{ $btnOutline }}">Uphold · void</button>
                            <button type="button" wire:click="resolve('{{ $r->id }}', 'rejected')"
                                    data-confirm="Reject the dispute on {{ $r->reference }}? The {{ $money((int) $r->fee_cents, $r->currency) }} fee stands and will be billed."
                                    class="{{ $btn }}" style="{{ $btnPrimary }}">Reject · charge stands</button>
                        </div>
                    </div>
                @empty
                    <div class="{{ $panel }} p-10 text-center">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">No open disputes</p>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">When a receiving business disputes a conversion, it lands here for a decision.</p>
                    </div>
                @endforelse
            </div>
            <div class="mt-5">{{ $disputes->links() }}</div>

        @elseif ($tab === 'payouts')
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <p class="flex-1 min-w-[12rem] text-[12.5px] text-gray-600 dark:text-gray-300">Failed and ready referrer payouts. Retrying sends a Stripe Connect transfer or Olux credit, per the referrer's payout method.</p>
                <button type="button" wire:click="retryAll" @disabled(! ($stats['failed'] + $stats['ready']))
                        data-confirm="Retry all {{ $stats['failed'] + $stats['ready'] }} failed and ready payouts?"
                        class="{{ $btn }} disabled:opacity-40" style="{{ $btnPrimary }}">
                    <span wire:loading.remove wire:target="retryAll">Retry all</span>
                    <span wire:loading wire:target="retryAll">Working…</span>
                </button>
            </div>
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($payouts as $po)
                    @php [$pb, $pf] = $statusPill($po->status); @endphp
                    <div class="px-5 py-3.5 flex flex-wrap items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}" wire:key="payout-{{ $po->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $siteName($po->site) }}</span>
                                <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $label($po->status) }}</span>
                                @if ($po->method)<span class="text-[11.5px] text-gray-500">{{ $po->method === 'olux_credit' ? 'Olux credit' : 'Stripe transfer' }}</span>@endif
                            </p>
                            <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">
                                {{ $po->referral?->reference }} · from {{ $siteName($po->referral?->toSite) }} · {{ $po->updated_at?->format('j M Y H:i') }}
                            </p>
                            @if ($po->failure)<p class="mt-0.5 text-[12px] text-rose-700 dark:text-rose-400">{{ $po->failure }}</p>@endif
                        </div>
                        <div class="text-right text-[12px] text-gray-600 dark:text-gray-300 shrink-0">
                            <p class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white">{{ $money((int) $po->net_cents, $po->currency) }}</p>
                            <p>fee {{ $money((int) $po->gross_cents, $po->currency) }} · Olux {{ $money((int) $po->olux_cents, $po->currency) }}</p>
                        </div>
                        <button type="button" wire:click="retry('{{ $po->id }}')"
                                data-confirm="Pay {{ $money((int) $po->net_cents, $po->currency) }} to {{ $siteName($po->site) }} now?"
                                class="{{ $btnOutline }}">
                            <span wire:loading.remove wire:target="retry('{{ $po->id }}')">Retry</span>
                            <span wire:loading wire:target="retry('{{ $po->id }}')">Working…</span>
                        </button>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No failed or waiting payouts.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $payouts->links() }}</div>

        @elseif ($tab === 'referrals')
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex-1 min-w-[14rem]"><x-field.search model="q" placeholder="Search by reference, customer or site…" /></div>
                <select wire:model.live="status" class="bkf-input !w-auto" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Modules\Network\Models\Referral::STATUSES as $s)
                        <option value="{{ $s }}">{{ $label($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($referrals as $r)
                    @php [$pb, $pf] = $statusPill($r->status); @endphp
                    <div class="px-5 py-3.5 flex flex-wrap items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}" wire:key="ref-{{ $r->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[12px] font-bold text-gray-500">{{ $r->reference }}</span>
                                <span class="text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $r->customer_name }}</span>
                                <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $label($r->status) }}</span>
                            </p>
                            <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">
                                {{ $siteName($r->fromSite) }} → {{ $siteName($r->toSite) }} · {{ $r->created_at?->format('j M Y') }}
                            </p>
                        </div>
                        <p class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white shrink-0">{{ $money((int) $r->fee_cents, $r->currency) }}</p>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No referrals match.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $referrals->links() }}</div>

        @else
            <div class="grid gap-3 @lg:grid-cols-2">
                @forelse ($members as $m)
                    <div class="{{ $panel }} p-5" wire:key="member-{{ $m->id }}">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $siteName($m->site) }}</p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $m->business_type ?: 'Business' }} · {{ $m->area ?: ($m->postcode ?: 'No area') }}</p>
                            </div>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full shrink-0 {{ $m->accepting ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $m->accepting ? 'Accepting' : 'Paused' }}</span>
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-2">
                            <p class="text-[11.5px] text-gray-500 dark:text-gray-400">Joined {{ $m->terms_accepted_at?->format('j M Y') }}@unless ($m->isMember()) · <span class="text-amber-700 dark:text-amber-400 font-semibold">old terms</span>@endunless</p>
                            <p class="text-right">
                                <span class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white">{{ $money((int) $m->fee_cents, $m->currency) }}</span>
                                <span class="block text-[11px] text-gray-500">per lead</span>
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="{{ $panel }} p-10 text-center @lg:col-span-2">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">No members yet</p>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Businesses appear here once they join the network.</p>
                    </div>
                @endforelse
            </div>
            <div class="mt-5">{{ $members->links() }}</div>
        @endif
    </div>

    <x-slot:quick>
        @if ($recentDisputes->isNotEmpty())
            <div class="{{ $panel }} p-5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Waiting on a decision</h3>
                @foreach ($recentDisputes as $d)
                    <button type="button" wire:click="setTab('disputes')" class="w-full flex items-center gap-2 py-2 text-left {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="flex-1 min-w-0 text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $d->reference }} · {{ $siteName($d->toSite) }}</span>
                        <span class="text-[12.5px] font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ $money((int) $d->fee_cents, $d->currency) }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">How the network works</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>A business refers a customer (with their consent) to a partner business.</li>
                <li>When the lead pays the partner within {{ config('network.conversion_window_days') }} days, the partner's fixed fee lands on their next Olux bill.</li>
                <li>The receiver may dispute within {{ config('network.dispute_days') }} days. Uphold voids the fee; reject lets it stand.</li>
                <li>Once collected, Olux keeps {{ rtrim(rtrim(number_format((float) config('network.olux_cut_pct'), 2), '0'), '.') }}% and the referrer gets the rest by Stripe transfer or Olux credit.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach (array_filter([
                \Illuminate\Support\Facades\Route::has('admin.accounts') ? ['Accounts', 'the businesses behind each site', route('admin.accounts')] : null,
                \Illuminate\Support\Facades\Route::has('admin.sales') ? ['Sales & payouts', 'template creator payouts', route('admin.sales')] : null,
                \Illuminate\Support\Facades\Route::has('admin.plans') ? ['Plans', 'which plans may refer', route('admin.plans')] : null,
            ]) as [$rl, $rd, $ru])
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
