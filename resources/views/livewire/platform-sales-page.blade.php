@php
    $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $salePill = fn (string $s) => match ($s) {
        'paid' => ['#dcfce7', '#15803d', 'Paid'],
        'refunded' => ['#ffe4e6', '#be123c', 'Refunded'],
        'pending' => ['#fef3c7', '#92400e', 'Pending'],
        default => ['#f3f4f6', '#374151', ucfirst($s)],
    };
    $payoutPill = fn (string $s) => match ($s) {
        'paid' => ['#dcfce7', '#15803d', 'Paid'],
        'failed' => ['#ffe4e6', '#be123c', 'Failed'],
        default => ['#fef3c7', '#92400e', ucfirst($s)],
    };
@endphp

<x-tri-layout title="Sales & payouts" subtitle="Template sales across the platform, and what each creator is owed."
    :labels="['📊 Numbers', '💷 Sales', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm">
            @foreach (['sales' => 'Sales', 'creators' => 'Creators', 'payouts' => 'Payouts'] as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>{{ $tl }}</button>
            @endforeach
        </div>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$gbp($stats['gross_30d'])" label="Template sales · 30 days" :sub="$stats['sales_30d'].' sales'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
        <x-tile accent="lime" :value="$gbp($stats['fees_30d'])" label="Platform revenue" sub="30 days"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$gbp($stats['owed'])" label="Owed to creators" :sub="$gbp($stats['held']).' on hold'"
                icon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        <x-tile accent="lavender" :value="$gbp($stats['paid_out'])" label="Paid out" sub="all time"
                icon="M5 13l4 4L19 7" />
        <x-tile accent="rose" :value="$stats['refunds_30d']" label="Refunds" sub="30 days"
                icon="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
    </div>

    <form wire:submit="saveFee" class="{{ $panel }} p-5 mt-3">
        <label class="block">
            <span class="bkf-label">Platform fee on creator sales (%)</span>
            <span class="flex gap-2">
                <input type="number" step="0.5" min="0" max="100" wire:model="feePercent" class="bkf-input w-full">
                <button type="submit" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Save</button>
            </span>
        </label>
        @error('feePercent')<p class="mt-1 text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
        <p class="mt-2 text-[11.5px] text-gray-500 dark:text-gray-400">Applies to new sales. Olux Studio's own templates are all platform revenue.</p>
    </form>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto">
        @if ($tab === 'sales')
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex-1 min-w-[14rem]"><x-field.search model="q" placeholder="Search by template or buyer…" /></div>
                <select wire:model.live="status" class="bkf-input !w-auto" aria-label="Status">
                    <option value="">Any status</option>
                    <option value="paid">Paid</option>
                    <option value="refunded">Refunded</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($sales as $p)
                    @php [$pb, $pf, $pl] = $salePill($p->status); @endphp
                    <div class="px-5 py-3.5 flex flex-wrap items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $p->template?->name ?? 'Deleted template' }}</span>
                                <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                                @if ($p->payout_id)<span class="text-[10.5px] font-bold text-gray-500">paid out</span>@endif
                            </p>
                            <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">
                                @if ($p->buyer)<a href="{{ route('admin.account', $p->user_id) }}" wire:navigate class="font-semibold hover:underline">{{ $p->buyer->name }}</a>@endif
                                · {{ $p->purchased_at?->format('j M Y H:i') }}
                                · creator: {{ $p->creator?->name ?? 'Olux Studio' }}
                            </p>
                        </div>
                        <div class="text-right text-[12px] text-gray-600 dark:text-gray-300 shrink-0">
                            <p class="font-display text-[16px] font-extrabold text-gray-900 dark:text-white">{{ $gbp((int) $p->price_cents) }}</p>
                            <p>fee {{ $gbp((int) $p->platform_fee_cents) }} · creator {{ $gbp((int) $p->creator_amount_cents) }}</p>
                        </div>
                        @if ($p->stripe_payment_intent_id)
                            <a href="https://dashboard.stripe.com/payments/{{ $p->stripe_payment_intent_id }}" target="_blank" rel="noopener" class="{{ $btnOutline }}">Stripe ↗</a>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No template sales yet.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $sales->links() }}</div>

        @elseif ($tab === 'creators')
            <div class="space-y-3">
                @forelse ($creators as $c)
                    @php $u = $c['user']; $connected = $u->stripe_account_id && $u->stripe_charges_enabled; @endphp
                    <div class="{{ $panel }} p-5">
                        <div class="flex flex-wrap items-center gap-4">
                            <x-avatar :src="$u->avatar" :initials="strtoupper(substr($u->name, 0, 1))" size="w-11 h-11" textSize="text-sm font-bold" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.account', $u->id) }}" wire:navigate class="block text-[15px] font-bold text-gray-900 dark:text-white truncate hover:underline">{{ $u->name }}</a>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400">
                                    {{ $c['sales'] }} {{ Str::plural('sale', $c['sales']) }} · {{ $gbp($c['gross']) }} gross · paid to date {{ $gbp($c['paid_to_date']) }}
                                    · <span class="{{ $connected ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }} font-semibold">{{ $connected ? 'Stripe connected' : 'No Stripe account' }}</span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-display text-xl font-extrabold text-gray-900 dark:text-white">{{ $gbp(max(0, $c['owed'])) }}</p>
                                <p class="text-[11.5px] text-gray-500 dark:text-gray-400">owed now @if ($c['held']) · {{ $gbp($c['held']) }} on hold @endif</p>
                            </div>
                            <button wire:click="startPayout('{{ $u->id }}')" @disabled($c['owed'] <= 0)
                                    class="{{ $btn }} disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">Pay out</button>
                        </div>
                        @if ($c['clawback'])
                            <p class="mt-2 text-[12px] text-rose-700 dark:text-rose-400">{{ $gbp($c['clawback']) }} of refunded sales already paid out is deducted from this payout.</p>
                        @endif
                    </div>
                @empty
                    <div class="{{ $panel }} p-10 text-center">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">No creator sales yet</p>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">When a creator's template sells, their share shows here.</p>
                    </div>
                @endforelse
            </div>

        @else
            <div class="{{ $panel }} overflow-hidden">
                @forelse ($history as $po)
                    @php [$pb, $pf, $pl] = $payoutPill($po->status); @endphp
                    <div class="px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $po->creator?->name ?? 'Unknown creator' }}</span>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                            <span class="text-[11.5px] text-gray-500">{{ $po->method === 'stripe' ? 'Stripe transfer' : 'Recorded manually' }}</span>
                            <span class="ml-auto font-display text-[16px] font-extrabold text-gray-900 dark:text-white">{{ \App\Support\Money::format($po->amount_cents, $po->currency) }}</span>
                        </div>
                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                            {{ $po->created_at->format('j M Y H:i') }} · {{ $po->purchases_count }} {{ Str::plural('sale', $po->purchases_count) }}
                            · by {{ $po->admin?->name ?? 'admin' }}
                            @if ($po->stripe_transfer_id) · <span class="font-mono">{{ $po->stripe_transfer_id }}</span>@endif
                        </p>
                        @if ($po->note)<p class="mt-1 text-[12px] {{ $po->status === 'failed' ? 'text-rose-700 dark:text-rose-400' : 'text-gray-600 dark:text-gray-300' }} whitespace-pre-line">{{ $po->note }}</p>@endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No payouts yet.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $history->links() }}</div>
        @endif
    </div>

    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Top earners owed</h3>
            @forelse ($creators->where('owed', '>', 0)->take(5) as $c)
                <button wire:click="startPayout('{{ $c['user']->id }}')" class="w-full flex items-center gap-2 py-2 text-left {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="flex-1 min-w-0 text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $c['user']->name }}</span>
                    <span class="text-[12.5px] font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ $gbp($c['owed']) }}</span>
                </button>
            @empty
                <p class="py-2 text-[12.5px] text-gray-500">Nobody is owed anything right now.</p>
            @endforelse
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">How payouts work</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>Every sale is collected on the platform's Stripe account.</li>
                <li>A creator's share becomes payable {{ $holdDays }} days after the sale, so most refunds land first.</li>
                <li>Pay out sends a Stripe transfer to the creator's connected account, or records a payment you made another way.</li>
                <li>Refunds remove the template from the buyer's library; if the creator was already paid, it comes off their next payout.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['Templates', 'catalog & review', route('admin.templates')], ['Accounts', 'buyers & creators', route('admin.accounts')]] as [$rl, $rd, $ru])
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

    @if ($paying)
        @php $pu = $paying['user']; $connected = $pu->stripe_account_id && $pu->stripe_charges_enabled; @endphp
        <x-side-drawer close="closePayout" width="max-w-lg">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Pay out</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $pu->name }}</h2>
            </x-slot:header>
            <form id="payout-form" wire:submit="payOut" class="p-6 space-y-5">
                <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] p-4">
                    <p class="text-[12px] text-gray-500 dark:text-gray-400">Amount</p>
                    <p class="font-display text-3xl font-extrabold text-gray-900 dark:text-white">{{ $gbp(max(0, $paying['owed'])) }}</p>
                    <p class="text-[12px] text-gray-600 dark:text-gray-300 mt-1">
                        {{ $gbp($paying['payable']) }} from sales older than {{ $holdDays }} days
                        @if ($paying['clawback']) − {{ $gbp($paying['clawback']) }} refunded after a previous payout @endif
                    </p>
                </div>
                <fieldset class="space-y-2">
                    <legend class="bkf-label">How</legend>
                    <label class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 {{ $connected ? 'cursor-pointer' : 'opacity-50' }} {{ $payMethod === 'stripe' ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                           @if ($payMethod === 'stripe') style="border-color:var(--primary)" @endif>
                        <input type="radio" wire:model.live="payMethod" value="stripe" @disabled(! $connected) class="mt-1">
                        <span>
                            <span class="block text-[13px] font-bold text-gray-900 dark:text-white">Stripe transfer</span>
                            <span class="block text-[12px] text-gray-500 dark:text-gray-400">{{ $connected ? 'Sent to their connected Stripe account.' : 'They haven\'t connected a Stripe account yet.' }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 cursor-pointer {{ $payMethod === 'manual' ? '' : 'border-gray-200 dark:border-white/[0.1]' }}"
                           @if ($payMethod === 'manual') style="border-color:var(--primary)" @endif>
                        <input type="radio" wire:model.live="payMethod" value="manual" class="mt-1">
                        <span>
                            <span class="block text-[13px] font-bold text-gray-900 dark:text-white">Record a payment made elsewhere</span>
                            <span class="block text-[12px] text-gray-500 dark:text-gray-400">E.g. a bank transfer. Nothing is sent from here.</span>
                        </span>
                    </label>
                    @error('payMethod')<p class="text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                </fieldset>
                <label class="block">
                    <span class="bkf-label">Note {{ $payMethod === 'manual' ? '' : '(optional)' }}</span>
                    <textarea wire:model="payNote" rows="3" maxlength="500" class="bkf-input w-full" placeholder="Bank reference, period covered…"></textarea>
                    @error('payNote')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
            </form>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closePayout" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="payout-form" @disabled($paying['owed'] <= 0)
                            data-confirm="Pay {{ $gbp(max(0, $paying['owed'])) }} to {{ $pu->name }}?"
                            class="{{ $btn }} min-w-[8rem] disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="payOut">{{ $payMethod === 'stripe' ? 'Send payout' : 'Record payout' }}</span>
                        <span wire:loading wire:target="payOut">Working…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif
</x-tri-layout>
