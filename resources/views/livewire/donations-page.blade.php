@php
    use App\Support\Money;
    $s = $this->stats;
    $cur = $s['currency'];
    $visible = $this->visible;
    $canManage = $this->canManage;
    $paymentsOn = $site->paymentsEnabled();
    $donateLinked = $this->donateLinked;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $pill = fn ($on) => 'shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors '.($on
        ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
        : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]');
    $statusClass = fn ($st) => $st === 'paid'
        ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300';
    $avatar = fn ($d) => ['#ec4899', '#6366f1', '#8b5cf6', '#0ea5e9', '#10b981'][abs(crc32($d->donor_name ?: $d->donor_email ?: 'anon')) % 5];
    $donor = fn ($d) => $d->donor_name ?: ($d->donor_email ?: 'Anonymous');
    $donateUrl = url('preview/'.$site->name.'/donate');
    $delta = $s['monthCents'] - $s['lastMonthCents'];
    $trendMax = max(1, collect($s['trend'])->max('cents'));
    $filterLabels = ['all' => 'All', 'paid' => 'Paid', 'pending' => 'Pending'];
@endphp
<x-tri-layout title="Donations" subtitle="Gifts from your supporters — who gave, how much, and how your donate page is set up." :site-name="$site->name"
    :labels="['📊 Giving', '💝 Donations', '⚡ Summary']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-3.5 py-1.5 rounded-full text-sm font-bold {{ $paymentsOn ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
                {{ $paymentsOn ? '● Accepting donations' : '○ Payments not connected' }}
            </span>
            <a href="{{ $donateUrl }}" target="_blank" class="{{ $btnSolid }} text-sm px-4 py-2">View public donate page ↗</a>
        </div>
    </x-slot:header>

    {{-- ── LEFT rail: giving at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="Money::format($s['monthCents'], $cur)" label="Raised this month"
                :sub="$s['monthCount'].' '.Str::plural('gift', $s['monthCount']).($s['lastMonthCents'] || $s['monthCents'] ? ' · '.($delta >= 0 ? '+' : '−').Money::format(abs($delta), $cur).' vs last month' : '')"
                icon="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        <x-tile accent="lime" :value="$s['raisedMajor']" label="All-time total" :sub="$s['count'].' paid '.Str::plural('gift', $s['count'])"
                icon="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$s['donors']" label="Donors" sub="unique supporters"
                icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        <x-tile accent="sky" :value="$s['avg']" label="Average gift" sub="per paid donation"
                icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        <x-tile accent="cocoa" :value="$s['repeatDonors']" label="Repeat donors" :sub="$s['repeatDonors'] ? 'gave more than once' : 'no repeat gifts yet'"
                icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        <x-tile accent="rose" :value="$s['largest'] ? $s['largest']->formattedAmount() : '—'" label="Largest gift"
                :sub="$s['largest'] ? 'from '.$donor($s['largest']) : 'none yet'"
                icon="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    {{-- ── Tabs ── --}}
    <div class="flex gap-2 overflow-x-auto no-scrollbar">
        <button type="button" wire:click="setTab('donations')" class="{{ $pill($tab === 'donations') }}">Donations <span class="opacity-60">{{ $s['counts']['all'] }}</span></button>
        <button type="button" wire:click="setTab('settings')" class="{{ $pill($tab === 'settings') }}">Settings</button>
    </div>

    @if ($tab === 'settings')
        {{-- ── Settings: the donate page's currency, suggested amounts and headline ── --}}
        <div class="{{ $panel }} p-6">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Donate page settings</p>
            <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1 mb-5">What visitors see on <a href="{{ $donateUrl }}" target="_blank" class="font-semibold underline">your donate page</a> — the suggested amounts become one-tap buttons.</p>
            @if ($canManage)
            <form wire:submit="saveSettings" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Headline</label>
                    <input wire:model="sHeadline" type="text" placeholder="Support our work" class="bkf-input w-full">
                    @error('sHeadline')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid sm:grid-cols-[1fr_12rem] gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Suggested amounts <span class="normal-case font-normal">— comma-separated</span></label>
                        <input wire:model="sAmounts" type="text" placeholder="5, 10, 25, 50" class="bkf-input w-full font-mono">
                        @error('sAmounts')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Currency</label>
                        <select wire:model="sCurrency" class="bkf-input w-full">
                            @foreach (['usd', 'eur', 'gbp', 'cad', 'aud'] as $code)
                                <option value="{{ $code }}">{{ strtoupper($code) }} — {{ Money::symbol($code) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (array_filter(array_map('trim', explode(',', $sAmounts)), 'is_numeric') as $a)
                        <span class="px-3 py-1 rounded-full text-[12px] font-bold" style="background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary)">{{ Money::format((int) round($a * 100), $sCurrency) }}</span>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">Save settings</button>
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="{{ $btnSolid }} text-sm px-4 py-2.5">Payment setup →</a>
                </div>
            </form>
            @else
                <dl class="grid sm:grid-cols-3 gap-3 text-sm">
                    <div><dt class="text-[11px] font-semibold uppercase text-gray-400">Headline</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $sHeadline }}</dd></div>
                    <div><dt class="text-[11px] font-semibold uppercase text-gray-400">Suggested amounts</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $sAmounts }}</dd></div>
                    <div><dt class="text-[11px] font-semibold uppercase text-gray-400">Currency</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ strtoupper($sCurrency) }}</dd></div>
                </dl>
                <p class="text-xs text-gray-400 mt-4">Only the site owner or admins can change these.</p>
            @endif
        </div>
    @else
        {{-- ── Toolbar: search · filter · sort · layout ── --}}
        <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[12rem]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search donor, email or message…" class="w-full" style="padding-left:2.25rem" />
                </div>
                <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="largest">Largest gift</option>
                </select>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            </div>
            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                @foreach ($filterLabels as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" class="{{ $pill($filter === $key) }}">
                        {{ $label }} <span class="opacity-60">{{ $s['counts'][$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($visible->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-rose-50 dark:bg-rose-500/10 text-2xl">💝</span>
                @if ($s['counts']['all'] === 0)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No donations yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        {{ $paymentsOn ? 'Share your donate page — every gift appears here the moment it is paid.' : 'Connect payments first so supporters can give by card, then share your donate page.' }}
                    </p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        @unless ($paymentsOn)
                            <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="inline-flex text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Connect payments</a>
                        @endunless
                        <a href="{{ $donateUrl }}" target="_blank" class="{{ $btnSolid }} text-sm px-4 py-2.5">Open donate page ↗</a>
                    </div>
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or filter.</p>
                    <button type="button" x-on:click="$wire.set('search', ''); $wire.setFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all donations</button>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($visible as $d)
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden" wire:key="don-{{ $d->id }}">
                        <div class="p-5 flex-1">
                            <div class="flex items-start gap-3">
                                <span class="w-11 h-11 rounded-full grid place-items-center text-white text-sm font-bold shrink-0" style="background:{{ $avatar($d) }}">
                                    {{ strtoupper(Str::substr($d->donor_name ?: $d->donor_email ?: 'A', 0, 1)) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $donor($d) }}</span>
                                    <span class="block text-[12px] text-gray-400 truncate">{{ $d->donor_email && $d->donor_name ? $d->donor_email.' · ' : '' }}{{ $d->created_at->format('j M Y') }}</span>
                                </span>
                                <span class="text-right shrink-0">
                                    <span class="block text-xl font-extrabold tabular-nums leading-none text-gray-900 dark:text-white">{{ $d->formattedAmount() }}</span>
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize {{ $statusClass($d->status) }}">{{ $d->status }}</span>
                                </span>
                            </div>
                            <p class="mt-3 text-[13px] italic line-clamp-2 min-h-[2.5em] {{ $d->message ? 'text-gray-600 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600' }}">
                                {{ $d->message ? '“'.$d->message.'”' : 'No message' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                            <span class="text-[11px] text-gray-400">{{ $d->created_at->diffForHumans() }}</span>
                            @if ($d->donor_email)
                                <span class="text-[11px] text-gray-400 truncate">· {{ $d->donor_email }}</span>
                            @endif
                            <button wire:click="deleteDonation('{{ $d->id }}')"
                                    data-confirm="Delete this donation record? The raised total will change. This cannot be undone."
                                    class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete donation record">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
            <div class="{{ $panel }} !rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">Donor</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                @unless ($compact)<th class="px-4 py-3">Message</th>@endunless
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($visible as $d)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="row-{{ $d->id }}">
                                    <td class="{{ $pad }}">
                                        <span class="flex items-center gap-2.5 min-w-0">
                                            @unless ($compact)
                                                <span class="w-8 h-8 rounded-full grid place-items-center text-white text-xs font-bold shrink-0" style="background:{{ $avatar($d) }}">{{ strtoupper(Str::substr($d->donor_name ?: $d->donor_email ?: 'A', 0, 1)) }}</span>
                                            @endunless
                                            <span class="min-w-0">
                                                <span class="block font-semibold text-gray-900 dark:text-white truncate">{{ $donor($d) }}</span>
                                                @if (! $compact && $d->donor_email && $d->donor_name)<span class="block text-[11px] text-gray-400 truncate">{{ $d->donor_email }}</span>@endif
                                            </span>
                                        </span>
                                    </td>
                                    <td class="{{ $pad }} text-right font-bold tabular-nums text-gray-900 dark:text-white whitespace-nowrap">{{ $d->formattedAmount() }}</td>
                                    @unless ($compact)
                                        <td class="{{ $pad }} text-[12px] italic text-gray-500 dark:text-gray-400 max-w-[14rem] truncate">{{ $d->message ? '“'.$d->message.'”' : '—' }}</td>
                                    @endunless
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $d->created_at->format('j M Y') }}</td>
                                    <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize {{ $statusClass($d->status) }}">{{ $d->status }}</span></td>
                                    <td class="{{ $pad }} text-right">
                                        <button wire:click="deleteDonation('{{ $d->id }}')"
                                                data-confirm="Delete this donation record? The raised total will change. This cannot be undone."
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete donation record">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
    </div>

    {{-- ══ RIGHT rail: trend · needs attention · top donors · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Last 6 months</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ Money::format((int) collect($s['trend'])->sum('cents'), $cur) }}</b> raised
            </p>
            <div class="flex items-end gap-1.5 h-24">
                @foreach ($s['trend'] as $m)
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end" title="{{ $m['label'] }} · {{ Money::format($m['cents'], $cur) }}">
                        <span class="w-full rounded-md {{ $m['cents'] ? '' : 'bg-gray-100 dark:bg-white/[0.06]' }}"
                              style="height:{{ max(4, round($m['cents'] / $trendMax * 100)) }}%;{{ $m['cents'] ? 'background:var(--primary)' : '' }}"></span>
                        <span class="text-[10px] font-semibold text-gray-400">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if (! $paymentsOn || ! $donateLinked || $s['pending'])
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @unless ($paymentsOn)
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">Payments aren't connected</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Supporters can't give by card yet — connect Stripe →</p>
                    </a>
                @endunless
                @unless ($donateLinked)
                    <a href="{{ url($site->name.'/connect') }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">Donate page isn't linked</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">No page or button on the site points to /donate — add one in Edit site →</p>
                    </a>
                @endunless
                @if ($s['pending'])
                    <button type="button" wire:click="setFilter('pending')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $s['pending'] }} unfinished {{ Str::plural('checkout', $s['pending']) }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Started but not paid — show them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if (count($s['topDonors']))
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Top donors</p>
            @php $topMax = max(1, $s['topDonors'][0]['cents']); @endphp
            <div class="space-y-2.5">
                @foreach ($s['topDonors'] as $t)
                    <div>
                        <span class="flex items-center justify-between gap-2 text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200">{{ $t['name'] }} <span class="text-gray-400">· {{ $t['gifts'] }} {{ Str::plural('gift', $t['gifts']) }}</span></span>
                            <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ Money::format($t['cents'], $cur) }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($t['cents'] / $topMax * 100) }}%;background:var(--primary)"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Payments', 'Stripe & payouts', route('site.payments', $site->name)],
                    ['Contacts', 'Your supporters', url($site->name.'/contacts')],
                    ['Edit site', 'Link the donate page', url($site->name.'/connect')],
                    ['Add-ons', 'Features & settings', url($site->name.'/addons')],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
