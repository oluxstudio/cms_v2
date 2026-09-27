@php $ps = $this->settings; $connected = $ps?->connect_account_id && $ps->connect_charges_enabled; $cur = strtoupper($site->currency ?? 'gbp'); @endphp

{{-- Full-screen canvas, dashboard-style: left rail | center | right rail --}}
<div class="min-h-full flex flex-col">

    {{-- ── Header row ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-5 shrink-0">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Payments</h1>
            <p class="mt-1 text-sm text-gray-400">How {{ ucwords(str_replace('-', ' ', $site->name)) }} takes money — Stripe connection, the master switch and your balance.</p>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-sm font-bold {{ $site->paymentsEnabled() ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
            {{ $site->paymentsEnabled() ? '● Accepting payments' : '○ Not accepting payments' }}
        </span>
    </div>

    {{-- ── Three panes: mobile swipe carousel · desktop side-by-side ── --}}
    <x-carousel :labels="['📊 Money', '💳 Payments', '⚡ Quick access']" :start="1">

        {{-- ════ LEFT RAIL — money at a glance ════ --}}
        <x-carousel.slide class="lg:!w-[25rem] lg:shrink-0 px-5 pb-24 lg:pb-6 space-y-4 max-h-full overflow-y-auto
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            @php $bal = $this->stripeBalance; $money = $this->money; @endphp

            <div class="rounded-3xl p-5 shadow-sm text-white" style="background:linear-gradient(135deg,#1c1d29,#2b2d3f)">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/60">Stripe balance</p>
                @if ($bal)
                    <p class="text-3xl font-extrabold mt-1">{{ strtoupper($bal['currency']) }} {{ number_format($bal['available_cents'] / 100, 2) }}</p>
                    <p class="text-[11px] text-white/60 mt-1">+ {{ number_format($bal['pending_cents'] / 100, 2) }} pending payout</p>
                @else
                    <p class="text-sm text-white/70 mt-2">Available once your Stripe account is connected.</p>
                @endif
            </div>

            <div class="rounded-3xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Collected · 30 days</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $cur }} {{ number_format($money['collected_cents'] / 100, 2) }}</p>
                <p class="text-[11px] text-gray-400 mt-1">paid invoices + orders</p>
            </div>

            <div class="rounded-3xl bg-amber-50 dark:bg-amber-500/10 p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Outstanding</p>
                <p class="text-3xl font-extrabold {{ $money['outstanding_cents'] > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white' }} mt-1">{{ $cur }} {{ number_format($money['outstanding_cents'] / 100, 2) }}</p>
                <a href="{{ url($site->name.'/invoices') }}" class="text-[11px] font-semibold text-indigo-500 hover:underline">unpaid invoices →</a>
            </div>
        </x-carousel.slide>

        {{-- ════ CENTER — Stripe connection + settings ════ --}}
        <x-carousel.slide class="lg:flex-1 px-3 lg:px-5 pb-24 lg:pb-8 overflow-y-auto no-scrollbar main-body">

            @if ($successMessage)<p class="mb-4 px-4 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>@endif
            @if ($errorMessage)<p class="mb-4 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm font-semibold text-rose-600">{{ $errorMessage }}</p>@endif

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
                                    <button type="button" wire:click="refreshStatus" class="px-4 py-2 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400">
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
                            <input wire:model="pubKey" type="text" placeholder="pk_live_…" class="w-full border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Secret key @if($hasSecret)<span class="text-emerald-500 normal-case font-normal">· saved</span>@endif</label>
                            <input wire:model="secretKey" type="password" placeholder="{{ $hasSecret ? '•••••••• (leave blank to keep)' : 'sk_live_…' }}" class="w-full border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Webhook signing secret</label>
                            <input wire:model="webhookSecret" type="password" placeholder="whsec_…" class="w-full border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                            <p class="text-[11px] text-gray-400 mt-1.5">Endpoint: <code class="text-indigo-500">{{ url($site->name.'/store/webhook') }}</code> (and /donate, /booking, /invoice) for <code>checkout.session.completed</code>.</p>
                        </div>
                    </div>
                </details>

                {{-- Currency + save --}}
                <div class="rounded-2xl border border-gray-100 dark:border-white/[0.05] bg-white dark:bg-[#1d1e2a] shadow-sm p-5 flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Currency</label>
                        <select wire:model="siteCurrency" class="w-full border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] text-gray-900 dark:text-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach(\App\Support\Money::options() as $code => $label)
                                <option value="{{ $code }}">{{ strtoupper($code) }} — {{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1.5">Used for every price, invoice, booking and checkout on this site.</p>
                    </div>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save payment settings</button>
                </div>
            </form>
        </x-carousel.slide>

        {{-- ════ RIGHT RAIL — Quick access ════ --}}
        <x-carousel.slide class="lg:!w-[270px] xl:!w-[290px] lg:shrink-0 flex flex-col px-5 pb-24 lg:pb-6 gap-3
                      max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] no-scrollbar">

            <div class="flex items-center justify-between pt-1 shrink-0">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Quick access</h3>
            </div>

            @foreach([
                ['Invoices',    $site->name.'/invoices',    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', '#eef2ff','#6366f1'],
                ['Orders',      $site->name.'/orders',      'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',                                                                             '#f0fdf4','#16a34a'],
                ['Bookings',    $site->name.'/bookings',    'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',                                 '#fffbeb','#d97706'],
                ['Donations',   $site->name.'/donations',   'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', '#fef2f2','#ef4444'],
                ['Marketplace', $site->name.'/marketplace', 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z', '#f5f3ff','#7c3aed'],
            ] as [$label, $href, $icon, $bg, $fg])
            <a href="{{ url($href) }}" class="shrink-0 flex items-center gap-3 bg-white dark:bg-[#1d1e2a] rounded-2xl px-4 py-3 shadow-sm border border-gray-100/80 dark:border-white/[0.05] hover:shadow-md transition-shadow group">
                <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:{{ $bg }};color:{{ $fg }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ $label }}</span>
                <svg class="w-4 h-4 ml-auto text-gray-300 group-hover:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endforeach
        </x-carousel.slide>
    </x-carousel>
</div>
