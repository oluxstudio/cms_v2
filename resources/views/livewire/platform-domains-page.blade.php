@php
    $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $pill = fn (string $s) => match ($s) {
        'registered' => ['#dcfce7', '#15803d', 'Registered'],
        'failed' => ['#ffe4e6', '#be123c', 'Failed'],
        'paid' => ['#e0f2fe', '#0369a1', 'Paid, registering'],
        'pending' => ['#fef3c7', '#92400e', 'Awaiting payment'],
        'checkout' => ['#f3f4f6', '#4b5563', 'Not paid'],
        'refunded' => ['#f3f4f6', '#374151', 'Refunded'],
        default => ['#f3f4f6', '#374151', ucfirst($s)],
    };
    $dot = fn (?bool $ok) => $ok === null ? 'bg-gray-300 dark:bg-white/20' : ($ok ? 'bg-emerald-500' : 'bg-rose-500');
@endphp

<x-tri-layout title="Domains" subtitle="Domain orders, connected client domains and the price list."
    :labels="['📊 Numbers', '🌐 Domains', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm">
            @foreach (['orders' => 'Orders'.($stats['failed'] ? ' ('.$stats['failed'].' failed)' : ''), 'connected' => 'Connected', 'prices' => 'Prices'] as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>{{ $tl }}</button>
            @endforeach
        </div>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$stats['registered']" label="Domains registered" :sub="$stats['connected'].' connected & verified'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-tile accent="rose" :value="$stats['failed']" label="Failed orders" :sub="$stats['failed'] ? 'paid, need retry' : 'all clear'"
                icon="M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
        <x-tile accent="cocoa" :value="$stats['expiring']" label="Expiring soon" sub="next 30 days"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lime" :value="$gbp($stats['revenue_30d'])" label="Domain sales" sub="30 days"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile :accent="$stats['driver'] === 'fake' ? 'rose' : 'sky'" :value="ucfirst($stats['driver'])" label="Registrar"
                :sub="$stats['driver'] === 'fake' ? 'test mode, nothing real' : 'live'"
                icon="M5 12h14M12 5l7 7-7 7" />
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto">
        @if ($tab !== 'prices')
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex-1 min-w-[14rem]"><x-field.search model="q" :placeholder="$tab === 'orders' ? 'Search domains…' : 'Search domains or sites…'" /></div>
                @if ($tab === 'orders')
                    <select wire:model.live="status" class="bkf-input !w-auto" aria-label="Status">
                        <option value="">Any status</option>
                        <option value="failed">Failed</option>
                        <option value="expiring">Expiring in 30 days</option>
                        <option value="registered">Registered</option>
                        <option value="paid">Paid, registering</option>
                        <option value="pending">Awaiting payment</option>
                        <option value="refunded">Refunded</option>
                    </select>
                @endif
            </div>
        @endif

        @if ($tab === 'orders')
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($orders as $o)
                    @php
                        [$pb, $pf, $pl] = $pill($o->status);
                        $soon = $o->status === 'registered' && $o->expires_at && $o->expires_at->isBetween(now(), now()->addDays(30));
                    @endphp
                    <div class="px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[14.5px] font-bold text-gray-900 dark:text-white">{{ $o->domain }}</span>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                            @if ($o->type === 'renew')<span class="text-[10.5px] font-bold text-gray-500">renewal</span>@endif
                            @if ($soon)<span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">expires {{ $o->expires_at->diffForHumans() }}</span>@endif
                            <span class="ml-auto flex items-center gap-2">
                                <span class="font-display text-[15px] font-extrabold text-gray-900 dark:text-white">{{ $gbp((int) $o->price_cents) }}</span>
                                @if ($o->status === 'failed')
                                    <button wire:click="retry('{{ $o->id }}')" wire:loading.attr="disabled" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Retry</button>
                                @endif
                                @if (in_array($o->status, ['failed', 'paid', 'pending'], true))
                                    <button wire:click="markRefunded('{{ $o->id }}')" data-confirm="Mark {{ $o->domain }} as refunded? Refund the payment in Stripe as well." class="{{ $btnOutline }}">Mark refunded</button>
                                @endif
                            </span>
                        </div>
                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                            @if ($o->user)<a href="{{ route('admin.account', $o->user_id) }}" wire:navigate class="font-semibold hover:underline">{{ $o->user->name }}</a>@endif
                            @if ($o->site) · site {{ $o->site->name }}@endif
                            · {{ $o->years }} {{ Str::plural('year', $o->years) }} · ordered {{ $o->created_at->format('j M Y') }}
                            @if ($o->expires_at) · expires {{ $o->expires_at->format('j M Y') }}@endif
                            @if ($o->plan) · with {{ ucfirst($o->plan) }} plan @endif
                        </p>
                        @if ($o->error)<p class="mt-1 text-[12.5px] {{ $o->status === 'failed' ? 'text-rose-700 dark:text-rose-400' : 'text-gray-600 dark:text-gray-300' }} break-words whitespace-pre-line">{{ $o->error }}</p>@endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No domain orders match.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $orders->links() }}</div>

        @elseif ($tab === 'connected')
            <div class="{{ $panel }} overflow-hidden" wire:loading.class="opacity-60">
                @forelse ($sites as $s)
                    @php $c = $checks[$s->id] ?? null; @endphp
                    <div class="px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="https://{{ $s->domain }}" target="_blank" rel="noopener" class="text-[14.5px] font-bold text-gray-900 dark:text-white hover:underline">{{ $s->domain }}</a>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full {{ $s->live ? 'text-white' : 'bg-gray-200 text-gray-700 dark:bg-white/[0.1] dark:text-gray-200' }}"
                                  @if ($s->live) style="background:var(--primary)" @endif>{{ $s->live ? 'Live' : 'Offline' }}</span>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full {{ $s->domain_verified_at ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $s->domain_verified_at ? 'Verified' : 'Not verified' }}</span>
                            <span class="ml-auto flex items-center gap-2">
                                <button wire:click="recheck('{{ $s->id }}')" wire:loading.attr="disabled" wire:target="recheck('{{ $s->id }}')" class="{{ $btnOutline }}">
                                    <span wire:loading.remove wire:target="recheck('{{ $s->id }}')">Re-check DNS</span>
                                    <span wire:loading wire:target="recheck('{{ $s->id }}')">Checking…</span>
                                </button>
                                @if ($s->live)
                                    <button wire:click="takeOffline('{{ $s->id }}')" data-confirm="Take {{ $s->domain }} offline? Visitors will no longer see the site there." class="{{ $btnOutline }}">Take offline</button>
                                @endif
                            </span>
                        </div>
                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                            site {{ $s->name }}
                            @if ($s->user) · <a href="{{ route('admin.account', $s->user_id) }}" wire:navigate class="font-semibold hover:underline">{{ $s->user->name }}</a>@endif
                            @if ($s->domain_verified_at) · verified {{ $s->domain_verified_at->diffForHumans() }}@endif
                        </p>
                        @if ($c)
                            <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-gray-700 dark:text-gray-300">
                                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full {{ $dot($c['records']) }}"></span>Points here{{ $c['found'] ? ' ('.implode(', ', array_slice($c['found'], 0, 2)).')' : '' }}</span>
                                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full {{ $dot($c['ownership']) }}"></span>Ownership record</span>
                                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full {{ $dot($c['ssl']) }}"></span>HTTPS</span>
                                <span class="text-gray-500">checked {{ \Illuminate\Support\Carbon::parse($c['at'])->diffForHumans() }}</span>
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No site has a custom domain yet.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $sites->links() }}</div>

        @else
            <form wire:submit="savePrices" class="{{ $panel }} p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Domain prices</h2>
                        <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Retail price per year, in the order shown to customers.
                            @if ($edited) <span class="font-semibold">Edited here.</span> @else Using the default list. @endif</p>
                    </div>
                    @if ($edited)
                        <button type="button" wire:click="resetPrices" data-confirm="Go back to the default price list?" class="{{ $btnOutline }}">Reset to defaults</button>
                    @endif
                </div>
                <div class="space-y-2">
                    @foreach ($prices as $i => $row)
                        <div class="flex items-start gap-2" wire:key="tld-{{ $i }}">
                            <label class="flex-1">
                                <span class="sr-only">Domain ending</span>
                                <span class="flex items-center gap-1">
                                    <span class="text-gray-500 font-bold">.</span>
                                    <input type="text" wire:model="prices.{{ $i }}.tld" placeholder="co.uk" class="bkf-input w-full font-mono">
                                </span>
                                @error("prices.$i.tld")<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="w-36">
                                <span class="sr-only">Price per year</span>
                                <span class="flex items-center gap-1">
                                    <span class="text-gray-500 font-bold">£</span>
                                    <input type="number" step="0.01" min="0" wire:model="prices.{{ $i }}.price" class="bkf-input w-full">
                                </span>
                                @error("prices.$i.price")<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <button type="button" wire:click="removeTld({{ $i }})" aria-label="Remove {{ $row['tld'] }}" class="{{ $btnOutline }} !px-3 mt-0.5">✕</button>
                        </div>
                    @endforeach
                </div>
                @error('prices')<p class="mt-2 text-[12px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <button type="button" wire:click="addTld" class="{{ $btnOutline }}">+ Add a domain ending</button>
                    <button type="submit" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Save prices</button>
                </div>
            </form>
        @endif
    </div>

    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Needs attention</h3>
            @forelse ($failedList as $o)
                <div class="py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <p class="text-[13px] font-semibold text-gray-800 dark:text-gray-100">{{ $o->domain }}</p>
                    <p class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ $o->user?->name }} · {{ $o->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <p class="py-2 text-[12.5px] text-gray-500">No failed orders.</p>
            @endforelse
        </div>

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Server setup</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>Domains point at: <b class="font-mono">{{ $dnsTarget ?: 'not set' }}</b></li>
                <li>Registrar: <b>{{ ucfirst($stats['driver']) }}</b>{{ $stats['driver'] === 'fake' ? ' (test mode, purchases aren\'t real)' : '' }}</li>
                <li>A failed order was already paid for: retry it, or refund it in Stripe and mark it refunded.</li>
            </ul>
        </div>

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['Accounts', 'owners of these domains', route('admin.accounts')], ['Dashboard', 'platform numbers', route('admin.dashboard')]] as [$rl, $rd, $ru])
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
