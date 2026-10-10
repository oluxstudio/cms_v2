@php
    use App\Support\Money;
    $ps = $this->settings;
    $connected = $ps?->connect_account_id && $ps->connect_charges_enabled;
    $onboarding = $ps?->connect_account_id && ! $ps->connect_charges_enabled;
    $ownKeys = ! $ps?->connect_account_id && $hasSecret && filled($ps?->stripe_publishable);
    $accepting = $site->paymentsEnabled();
    $code = $site->currency ?? 'gbp';
    $cur = strtoupper($code);
    $bal = $this->stripeBalance;
    $money = $this->money;
    $month = $this->monthTakings;
    $fee = $this->feePct;
    $testMode = $this->testMode;
    $features = $this->moneyFeatures;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $input = 'w-full border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500';
    $connectionLabel = $connected ? 'Connected' : ($onboarding ? 'Onboarding' : ($ownKeys ? 'Own API keys' : 'Not connected'));
    $featureLabels = collect($features)->pluck('label', 'key');
    $state = fn ($f) => ! $f['enabled'] ? 'off' : ($accepting ? 'ready' : 'setup');
    $stateStyle = [
        'ready' => ['Ready', 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'],
        'setup' => ['Needs setup', 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300'],
        'off' => ['Off', 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400'],
    ];
    $steps = [
        ['Connect your Stripe account', 'Business and bank details on Stripe\'s secure form — no API keys.', (bool) $ps?->connect_account_id || $ownKeys],
        ['Finish Stripe onboarding', 'Stripe confirms your details and enables charges.', $connected || $ownKeys],
        ['Switch on Accept payments', 'Your store, bookings, donations and invoices start taking cards.', $accepting],
    ];
@endphp
<x-tri-layout title="Payments" :subtitle="'How '.ucwords(str_replace('-', ' ', $site->name)).' takes money — Stripe connection, the master switch and your balance.'" :site-name="$site->name"
    :labels="['📊 Money', '💳 Payments', '⚡ What uses it']">

    <x-slot:header>
        <span class="px-3.5 py-1.5 rounded-full text-sm font-bold {{ $accepting ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
            {{ $accepting ? '● Accepting payments' : '○ Not accepting payments' }}
        </span>
    </x-slot:header>

    {{-- ── LEFT rail: money at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile :accent="$connected || $ownKeys ? 'ink' : 'rose'" wide :value="$connectionLabel" label="Stripe connection"
                :sub="$accepting ? 'accepting payments' : ($connected || $ownKeys ? 'switch is off' : 'not taking payments')"
                icon="M13.8 10.2a4 4 0 00-5.6 0l-4 4a4 4 0 105.6 5.6l1.1-1.1m-.7-4.9a4 4 0 005.6 0l4-4a4 4 0 00-5.6-5.6l-1.1 1.1" />
        <x-tile :accent="$connected || $ownKeys ? 'lime' : 'rose'" :value="$connected || $ownKeys ? 'Yes' : 'No'" label="Charges & payouts"
                :sub="$connected ? 'enabled by Stripe' : ($ownKeys ? 'your own Stripe account' : ($onboarding ? 'Stripe needs details' : 'connect to enable'))"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        <x-tile accent="lavender" :value="Money::format($month['total'], $code)" label="Taken this month"
                :sub="count($month['rows']) ? (collect($month['rows'])->filter()->keys()->map(fn ($k) => $featureLabels[$k] ?? ucfirst($k))->implode(' · ') ?: 'nothing yet') : 'no money features on'"
                icon="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="rtrim(rtrim(number_format($fee, 2), '0'), '.').'%'" label="Platform fee" :sub="$fee > 0 ? 'per sale, on top of Stripe' : 'none on your plan'"
                icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
        @if ($bal)
            <x-tile accent="sky" :value="Money::format($bal['available_cents'], $bal['currency'])" label="Stripe balance"
                    :sub="'+ '.Money::format($bal['pending_cents'], $bal['currency']).' pending payout'"
                    icon="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
        @else
            <x-tile accent="sky" value="—" label="Stripe balance" sub="shown once Stripe is connected"
                    icon="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
        @endif
        <x-tile accent="lime" :value="Money::format($money['collected_cents'], $code)" label="Collected · 30 days" sub="paid invoices + orders"
                icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        @if ($site->hasFeature('invoices'))
            <x-tile :accent="$money['outstanding_cents'] > 0 ? 'rose' : 'sky'" :value="Money::format($money['outstanding_cents'], $code)" label="Outstanding"
                    sub="unpaid invoices →" :href="url($site->name.'/invoices')"
                    icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        @endif
    </div>
    </x-slot:rail>

    <div class="space-y-4">

        @if ($successMessage)<p class="px-4 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>@endif
        @if ($errorMessage)<p class="px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm font-semibold text-rose-600">{{ $errorMessage }}</p>@endif

        {{-- ── Step card (not set up yet) · account details (connected) ── --}}
        @if ($accepting && ($connected || $ownKeys))
            <div class="{{ $panel }} p-6">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center shrink-0 bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Your site is taking payments</p>
                        <p class="text-[13px] text-gray-500 dark:text-gray-400">Card payments go straight to {{ $connected ? 'your connected Stripe account' : 'the Stripe account behind your API keys' }}.</p>
                    </div>
                </div>
                <dl class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ([
                        ['Account', $connected ? $ps->connect_account_id : 'Own API keys'],
                        ['Mode', $testMode === null ? '—' : ($testMode ? 'Test mode' : 'Live')],
                        ['Currency', $cur],
                        ['Platform fee', rtrim(rtrim(number_format($fee, 2), '0'), '.').'%'],
                    ] as [$k, $v])
                        <div class="rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $k }}</dt>
                            <dd class="text-[13px] font-bold text-gray-900 dark:text-white truncate font-mono" title="{{ $v }}">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($testMode)
                    <p class="mt-3 text-[12px] font-semibold text-amber-600">Test mode — no real money moves. Use live keys (or a live platform) before launch.</p>
                @endif
            </div>
        @else
            <div class="{{ $panel }} p-6">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Start taking payments in 3 steps</p>
                <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1 mb-4">Everything below — store, bookings, donations, invoices — uses this one connection.</p>
                <ol class="space-y-2.5">
                    @foreach ($steps as $i => [$title, $hint, $done])
                        @php $current = ! $done && collect($steps)->take($i)->every(fn ($s) => $s[2]); @endphp
                        <li class="flex items-start gap-3 rounded-2xl px-3.5 py-3 {{ $current ? 'bg-indigo-50 dark:bg-indigo-500/10 ring-1 ring-indigo-200 dark:ring-indigo-500/30' : 'bg-gray-50 dark:bg-white/[0.04]' }}">
                            <span class="w-7 h-7 rounded-full grid place-items-center text-[12px] font-extrabold shrink-0 {{ $done ? 'bg-emerald-500 text-white' : ($current ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-[#1d1e2a] text-gray-400 border border-gray-200 dark:border-white/[0.1]') }}">{{ $done ? '✓' : $i + 1 }}</span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold {{ $done ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white' }}">{{ $title }}</span>
                                <span class="block text-[12px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        <form wire:submit="save" class="space-y-4">

            {{-- Master switch --}}
            <label class="flex items-center justify-between gap-3 px-5 py-4 rounded-2xl border bg-white dark:bg-[#1d1e2a] shadow-sm {{ $acceptPayments ? 'border-emerald-300 dark:border-emerald-500/40' : 'border-gray-100 dark:border-white/[0.05]' }}">
                <span>
                    <span class="block text-sm font-bold text-gray-900 dark:text-white">Accept payments</span>
                    <span class="block text-xs text-gray-400 mt-0.5">Off = your store, bookings, donations and invoices take no card payments.</span>
                </span>
                <input type="checkbox" wire:model="acceptPayments" class="w-6 h-6 rounded-md text-emerald-600 focus:ring-emerald-500">
            </label>

            {{-- Connect card --}}
            <div class="rounded-2xl border border-indigo-100 dark:border-indigo-500/20 bg-white dark:bg-[#1d1e2a] shadow-sm p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">Your Stripe account <span class="ml-1 text-[9px] font-extrabold uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-600 text-white align-middle">Recommended</span></p>
                        <p class="text-xs text-gray-400 mt-1 max-w-md">No API keys — enter your business and bank details on Stripe's secure form; payments go straight to your account.</p>
                        @if ($connected)
                            <p class="mt-2 text-xs font-bold text-emerald-600">✓ Connected ({{ $ps->connect_account_id }}) — ready to take payments.</p>
                        @elseif ($ps?->connect_account_id)
                            <p class="mt-2 text-xs font-bold text-amber-600">Onboarding started — Stripe needs a few more details before you can take payments.</p>
                        @else
                            <p class="mt-2 text-xs text-gray-400">Not connected yet.</p>
                        @endif
                    </div>
                    <div class="flex flex-col items-stretch gap-2 shrink-0">
                        @if ($this->connectAvailable())
                            <a href="{{ url($site->name.'/payments/connect') }}"
                               class="px-4 py-2 rounded-xl text-sm font-semibold text-white text-center bg-indigo-600 hover:bg-indigo-700">
                                {{ $connected ? 'Manage on Stripe →' : ($ps?->connect_account_id ? 'Continue onboarding →' : 'Connect with Stripe →') }}
                            </a>
                            @if ($ps?->connect_account_id)
                                <button type="button" wire:click="refreshStatus" class="{{ $btnSolid }} px-4 py-2 text-xs">
                                    <span wire:loading.remove wire:target="refreshStatus">Refresh status</span>
                                    <span wire:loading wire:target="refreshStatus">Asking Stripe…</span>
                                </button>
                            @endif
                        @else
                            <p class="text-[11px] font-semibold text-amber-600 max-w-[180px]">Not available on this installation — the platform's Stripe Connect keys aren't configured.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Advanced keys --}}
            <details class="rounded-2xl border border-gray-100 dark:border-white/[0.05] bg-white dark:bg-[#1d1e2a] shadow-sm p-5" @if($hasSecret && ! $ps?->connect_account_id) open @endif>
                <summary class="text-xs font-bold text-gray-600 dark:text-gray-300 cursor-pointer">Advanced: use your own Stripe API keys instead</summary>
                <div class="mt-4 grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Publishable key</label>
                        <input wire:model="pubKey" type="text" placeholder="pk_live_…" class="{{ $input }} font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Secret key @if($hasSecret)<span class="text-emerald-500 normal-case font-normal">· saved</span>@endif</label>
                        <input wire:model="secretKey" type="password" placeholder="{{ $hasSecret ? '•••••••• (leave blank to keep)' : 'sk_live_…' }}" class="{{ $input }} font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Webhook signing secret</label>
                        <input wire:model="webhookSecret" type="password" placeholder="whsec_…" class="{{ $input }} font-mono">
                        <p class="text-[11px] text-gray-400 mt-1.5">Endpoint: <code class="text-indigo-500">{{ url($site->name.'/store/webhook') }}</code> (and /donate, /booking, /invoice) for <code>checkout.session.completed</code>.</p>
                    </div>
                </div>
            </details>

            {{-- Currency + save --}}
            <div class="rounded-2xl border border-gray-100 dark:border-white/[0.05] bg-white dark:bg-[#1d1e2a] shadow-sm p-5 flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Currency</label>
                    <select wire:model="siteCurrency" class="{{ $input }}">
                        @foreach(Money::options() as $optCode => $label)
                            <option value="{{ $optCode }}">{{ strtoupper($optCode) }} — {{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1.5">Used for every price, invoice, booking and checkout on this site.</p>
                </div>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save payment settings</button>
            </div>
        </form>

        {{-- ── What each money feature takes (this month) ── --}}
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05] flex items-center justify-between gap-2">
                <p class="text-[13px] font-extrabold text-gray-900 dark:text-white">Taken this month, by feature</p>
                <span class="text-[13px] font-extrabold tabular-nums text-gray-900 dark:text-white">{{ Money::format($month['total'], $code) }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                @foreach ($features as $f)
                    @php [$stLabel, $stClass] = $stateStyle[$state($f)]; @endphp
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold {{ $f['enabled'] ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $f['label'] }}</span>
                            <span class="block text-[11px] text-gray-400">{{ $f['hint'] }}{{ $f['key'] === 'memberships' && $f['enabled'] ? ' · monthly recurring' : '' }}</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $stClass }}">{{ $stLabel }}</span>
                        <span class="w-24 text-right text-[13px] font-bold tabular-nums {{ ($month['rows'][$f['key']] ?? 0) ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">
                            {{ array_key_exists($f['key'], $month['rows']) ? Money::format($month['rows'][$f['key']], $code) : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══ RIGHT rail: what uses payments · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">What uses payments</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ collect($features)->where('enabled', true)->count() }}</b> of {{ count($features) }} money features on
            </p>
            <div class="space-y-1.5">
                @foreach ($features as $f)
                    @php [$stLabel, $stClass] = $stateStyle[$state($f)]; @endphp
                    <a href="{{ $f['enabled'] ? $f['href'] : url($site->name.'/addons') }}" wire:navigate class="flex items-center gap-2 rounded-xl px-2.5 py-2 hover:bg-gray-50 dark:hover:bg-white/[0.04] group">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ ['ready' => 'bg-emerald-500', 'setup' => 'bg-amber-500', 'off' => 'bg-gray-300 dark:bg-gray-600'][$state($f)] }}"></span>
                        <span class="text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 group-hover:underline">{{ $f['label'] }}</span>
                        <span class="ml-auto px-2 py-0.5 rounded-full text-[10.5px] font-semibold {{ $stClass }}">{{ $stLabel }}</span>
                    </a>
                @endforeach
            </div>
            @unless ($accepting)
                <p class="mt-3 text-[11.5px] text-amber-700 dark:text-amber-300">Features marked “Needs setup” can't take card payments until Stripe is connected and Accept payments is on.</p>
            @endunless
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_filter([
                    ['Add-ons', 'Switch features on', url($site->name.'/addons')],
                    $site->hasFeature('invoices') ? ['Invoices', 'Bill & get paid', url($site->name.'/invoices')] : null,
                    $site->hasFeature('store') ? ['Orders', 'Store sales', url($site->name.'/orders')] : null,
                    $site->hasFeature('bookings') ? ['Bookings', 'Deposits taken', url($site->name.'/bookings')] : null,
                    ['Plan', 'Fees & limits', route('account.subscription')],
                    ['Contacts', 'Your customers', url($site->name.'/contacts')],
                ]) as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
