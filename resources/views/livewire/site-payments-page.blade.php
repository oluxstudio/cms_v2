@php $ps = $this->settings; $connected = $ps?->connect_account_id && $ps->connect_charges_enabled; $cur = strtoupper($site->currency ?? 'gbp'); @endphp
<div class="main-body max-w-4xl mx-auto p-5 lg:p-8">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Payments</h1>
            <p class="mt-1 text-sm text-gray-400">How {{ ucwords(str_replace('-', ' ', $site->name)) }} takes money — Stripe connection, the master switch and your balance.</p>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-sm font-bold {{ $site->paymentsEnabled() ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
            {{ $site->paymentsEnabled() ? '● Accepting payments' : '○ Not accepting payments' }}
        </span>
    </div>

    @if ($successMessage)<p class="mb-4 px-4 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>@endif
    @if ($errorMessage)<p class="mb-4 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm font-semibold text-rose-600">{{ $errorMessage }}</p>@endif

    {{-- ── Money at a glance ── --}}
    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        @php $bal = $this->stripeBalance; $money = $this->money; @endphp
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Stripe balance</p>
            @if ($bal)
                <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ strtoupper($bal['currency']) }} {{ number_format($bal['available_cents'] / 100, 2) }}</p>
                <p class="text-[11px] text-gray-400">+ {{ number_format($bal['pending_cents'] / 100, 2) }} pending payout</p>
            @else
                <p class="text-sm text-gray-400 mt-2">Available once your Stripe account is connected.</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Collected · 30 days</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $cur }} {{ number_format($money['collected_cents'] / 100, 2) }}</p>
            <p class="text-[11px] text-gray-400">paid invoices + orders</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Outstanding</p>
            <p class="text-2xl font-extrabold {{ $money['outstanding_cents'] > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white' }} mt-1">{{ $cur }} {{ number_format($money['outstanding_cents'] / 100, 2) }}</p>
            <a href="{{ url($site->name.'/invoices') }}" class="text-[11px] font-semibold text-indigo-500 hover:underline">unpaid invoices →</a>
        </div>
    </div>

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
</div>
