@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $pill = fn ($on) => 'shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors '.($on
        ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
        : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]');
    $statusClass = fn ($s) => match ($s) {
        'subscribed', 'sent' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'pending', 'scheduled' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300',
        'sending' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300',
        'bounced' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300',
        default => 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400',
    };
    $healthColor = ['subscribed' => '#10b981', 'pending' => '#f59e0b', 'unsubscribed' => '#9ca3af', 'bounced' => '#f43f5e'];
    $quotaValue = $quota['left'] === null ? 'Unlimited' : number_format($quota['left']);
    $quotaSub = $quota['limit'] === null ? $quota['plan'].' plan' : number_format($quota['used']).' of '.number_format($quota['limit']).' used';
    $growthSub = $stats['newLastMonth'] ? number_format($stats['newLastMonth']).' last month' : 'new since '.now()->startOfMonth()->format('j M');
    $upgradeUrl = route('account.subscription');
@endphp
<x-tri-layout title="Newsletter" subtitle="Grow your email list and send campaigns to your subscribers." :site-name="$site->name"
    :labels="['📊 Overview', '✉️ Newsletter', '⚡ Summary']">

    {{-- ── LEFT rail: the list at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="number_format($stats['subscribed'])" label="Subscribers" :sub="$stats['pending'] ? number_format($stats['pending']).' awaiting confirmation' : 'receiving your emails'"
                icon="M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        <x-tile accent="lime" :value="'+'.number_format($stats['newMonth'])" label="Growth this month" :sub="$growthSub"
                icon="M3 13.5L9 7.5l4 4L21 3.5M21 3.5h-5m5 0v5M4 20h16" />
        <x-tile accent="lavender" :value="number_format($stats['sentCampaigns'])" label="Campaigns sent" :sub="number_format($stats['delivered']).' emails delivered'"
                icon="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
        <x-tile accent="sky" :value="$stats['openRate'] === null ? '—' : $stats['openRate'].'%'" label="Average open rate" :sub="$stats['clickRate'] === null ? 'after your first send' : $stats['clickRate'].'% clicked'"
                icon="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.5 12C3.7 7.9 7.5 5 12 5s8.3 2.9 9.5 7c-1.2 4.1-5 7-9.5 7s-8.3-2.9-9.5-7z" />
        <x-tile accent="rose" :value="number_format($stats['unsubscribed'])" label="Unsubscribes" :sub="number_format($stats['unsubMonth']).' this month'"
                icon="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
        <x-tile accent="cocoa" :value="$quotaValue" label="Sends left this month" :sub="$quotaSub" :href="$quota['left'] !== null && $quota['left'] < 100 ? $upgradeUrl : null"
                icon="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @if ($notice)
        <div class="{{ $panel }} !rounded-2xl px-4 py-3 flex items-center gap-3 text-sm text-emerald-800 dark:text-emerald-200 !bg-emerald-50 dark:!bg-emerald-500/10 !border-emerald-100 dark:!border-emerald-500/20">
            <span class="flex-1">{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" class="text-xs font-semibold opacity-70 hover:opacity-100">✕</button>
        </div>
    @endif

    @unless ($editing)
    {{-- ── Tabs ── --}}
    <div class="flex gap-2">
        <button type="button" wire:click="setTab('subscribers')" class="{{ $pill($tab === 'subscribers') }}">Subscribers <span class="opacity-60">{{ number_format($stats['total']) }}</span></button>
        <button type="button" wire:click="setTab('campaigns')" class="{{ $pill($tab === 'campaigns') }}">Campaigns <span class="opacity-60">{{ number_format($stats['campaigns']) }}</span></button>
    </div>

    {{-- ── Toolbar: search · filters · layout · new ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.250ms="search" :placeholder="$tab === 'subscribers' ? 'Search by email or name…' : 'Search campaigns by subject…'" class="w-full" style="padding-left:2.25rem" />
            </div>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            @if ($tab === 'subscribers')
                <button type="button" wire:click="exportCsv" class="{{ $btnSolid }} text-sm px-3.5 py-2.5" title="Download the filtered list as CSV">Export</button>
                @if ($canManage)
                    <button type="button" wire:click="$toggle('showImport')" class="{{ $btnSolid }} text-sm px-3.5 py-2.5">Import</button>
                    <button type="button" wire:click="$toggle('showAdd')"
                            class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        New subscriber
                    </button>
                @endif
            @elseif ($canManage)
                <button type="button" wire:click="newCampaign"
                        class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New campaign
                </button>
            @endif
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @if ($tab === 'subscribers')
                @foreach (['all' => ['All', $stats['total']], 'subscribed' => ['Subscribed', $stats['subscribed']], 'pending' => ['Pending', $stats['pending']], 'unsubscribed' => ['Unsubscribed', $stats['unsubscribed']], 'bounced' => ['Bounced', $stats['bounced']]] as $key => [$label, $n])
                    <button type="button" wire:click="setStatus('{{ $key }}')" class="{{ $pill($status === $key) }}">{{ $label }} <span class="opacity-60">{{ number_format($n) }}</span></button>
                @endforeach
                @foreach ($tags as $t)
                    <button type="button" wire:click="setTag(@js($t))" class="{{ $pill($tag === $t) }}">#{{ $t }}</button>
                @endforeach
            @else
                @foreach (['all' => 'All', 'draft' => 'Drafts', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent'] as $key => $label)
                    <button type="button" wire:click="setCampaignFilter('{{ $key }}')" class="{{ $pill($campaignFilter === $key) }}">{{ $label }} <span class="opacity-60">{{ $campaignCounts[$key] ?? 0 }}</span></button>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ── Add subscriber ── --}}
    @if ($tab === 'subscribers' && $showAdd && $canManage)
        <form wire:submit="addSubscriber" class="{{ $panel }} !rounded-2xl p-5 space-y-3">
            <p class="text-[15px] font-bold text-gray-900 dark:text-white">Add a subscriber</p>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <x-field.text label="Email" model="addEmail" type="email" placeholder="name@example.com" />
                    @error('addEmail') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <x-field.text label="Name (optional)" model="addName" />
                <x-field.text label="Tags (optional)" model="addTags" placeholder="vip, customers" />
            </div>
            <p class="text-[12px] text-gray-500 dark:text-gray-400">Only add people who asked to hear from you — they're subscribed straight away, without a confirmation email.</p>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)">Add subscriber</button>
                <button type="button" wire:click="$set('showAdd', false)" class="{{ $btnSolid }} text-sm px-4 py-2">Cancel</button>
            </div>
        </form>
    @endif

    {{-- ── Import CSV ── --}}
    @if ($tab === 'subscribers' && $showImport && $canManage)
        <form wire:submit="importCsv" class="{{ $panel }} !rounded-2xl p-5 space-y-3">
            <p class="text-[15px] font-bold text-gray-900 dark:text-white">Import a CSV</p>
            <p class="text-[13px] text-gray-500 dark:text-gray-400">Columns: <code class="font-mono">email, name, tags</code> (header row optional; separate several tags with <code class="font-mono">;</code>). Existing addresses gain the tags; people who unsubscribed stay unsubscribed.</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <input type="file" wire:model="importFile" accept=".csv,text/csv,text/plain"
                           class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:font-semibold file:bg-gray-100 dark:file:bg-white/[0.08] file:text-gray-700 dark:file:text-gray-200">
                    @error('importFile') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <x-field.text model="importTags" placeholder="Tag everyone in this file (optional)" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold" style="background:var(--primary);color:var(--on-primary)" wire:loading.attr="disabled">Import</button>
                <button type="button" wire:click="$set('showImport', false)" class="{{ $btnSolid }} text-sm px-4 py-2">Close</button>
            </div>
            @if ($importResult)
                <p class="text-sm text-emerald-700 dark:text-emerald-300">Imported: {{ $importResult['added'] }} added · {{ $importResult['updated'] }} updated · {{ $importResult['skipped'] }} skipped (invalid email).</p>
            @endif
        </form>
    @endif

    {{-- ═════════ SUBSCRIBERS ═════════ --}}
    @if ($tab === 'subscribers')
        @if ($subscriberList->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                @if ($stats['total'] === 0)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No subscribers yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Signups from your site's newsletter box land here. You can also add people or import a CSV.</p>
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                    <button type="button" x-on:click="$wire.set('search', ''); $wire.setStatus('all'); $wire.set('tag', '')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show everyone</button>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($subscriberList as $s)
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden" wire:key="sub-{{ $s->id }}">
                        <div class="p-5 flex-1">
                            <div class="flex items-start gap-3">
                                <span class="w-10 h-10 rounded-xl grid place-items-center shrink-0 font-bold text-sm bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">{{ mb_strtoupper(mb_substr($s->name ?: $s->email, 0, 1)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[14px] font-bold text-gray-900 dark:text-white truncate" title="{{ $s->email }}">{{ $s->email }}</span>
                                    <span class="block text-[12px] text-gray-400 truncate">{{ $s->name ?: 'No name' }} · joined {{ $s->created_at?->diffForHumans() }}</span>
                                </span>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass($s->status) }}">{{ ucfirst($s->status) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5 min-h-[1.5rem]">
                                @foreach ($s->tags ?? [] as $t)
                                    <button type="button" wire:click="setTag(@js($t))" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 hover:underline">#{{ $t }}</button>
                                @endforeach
                                @if ($s->source)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] text-gray-400 truncate max-w-[12rem]" title="{{ $s->source }}">via {{ Str::limit($s->source, 40) }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($canManage)
                            <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                                @if ($s->status === 'pending')
                                    <button type="button" wire:click="resendConfirmation('{{ $s->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Resend confirmation</button>
                                @endif
                                @if (in_array($s->status, ['subscribed', 'pending'], true))
                                    <button type="button" wire:click="unsubscribeSubscriber('{{ $s->id }}')" data-confirm="Unsubscribe {{ $s->email }}? They won't get any more campaigns." class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Unsubscribe</button>
                                @endif
                                <button type="button" wire:click="deleteSubscriber('{{ $s->id }}')" data-confirm="Delete {{ $s->email }} from your list for good?" title="Delete"
                                        class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        @endif
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
                                <th class="px-4 py-3">Subscriber</th>
                                <th class="px-4 py-3">Status</th>
                                @unless ($compact)<th class="px-4 py-3">Tags</th><th class="px-4 py-3">Joined</th>@endunless
                                <th class="w-28 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($subscriberList as $s)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="row-{{ $s->id }}">
                                    <td class="{{ $pad }}">
                                        <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[18rem]">{{ $s->email }}</span>
                                        @unless ($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[18rem]">{{ $s->name ?: '—' }}</span>@endunless
                                    </td>
                                    <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass($s->status) }}">{{ ucfirst($s->status) }}</span></td>
                                    @unless ($compact)
                                        <td class="{{ $pad }} text-[12px] text-gray-500 dark:text-gray-400">{{ implode(', ', $s->tags ?? []) ?: '—' }}</td>
                                        <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $s->created_at?->format('j M Y') }}</td>
                                    @endunless
                                    <td class="{{ $pad }} text-right whitespace-nowrap">
                                        @if ($canManage)
                                            @if (in_array($s->status, ['subscribed', 'pending'], true))
                                                <button type="button" wire:click="unsubscribeSubscriber('{{ $s->id }}')" data-confirm="Unsubscribe {{ $s->email }}?" class="text-[12px] font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-white">Unsubscribe</button>
                                            @endif
                                            <button type="button" wire:click="deleteSubscriber('{{ $s->id }}')" data-confirm="Delete {{ $s->email }} from your list for good?" class="ml-2 text-[12px] font-semibold text-rose-500 hover:text-rose-600">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @if ($subscriberList->hasPages())
            <div>{{ $subscriberList->links() }}</div>
        @endif
    @endif

    {{-- ═════════ CAMPAIGNS ═════════ --}}
    @if ($tab === 'campaigns')
        @if ($campaigns->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                    <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $campaignCounts['all'] ? 'Nothing matches' : 'No campaigns yet' }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Write an update, send yourself a test, then send it now or schedule it.</p>
                @if ($canManage && ! $campaignCounts['all'])
                    <button type="button" wire:click="newCampaign" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Write the first campaign</button>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($campaigns as $c)
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden" wire:key="camp-{{ $c->id }}">
                        <div class="p-5 flex-1">
                            <div class="flex items-start gap-3">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate" title="{{ $c->subject }}">{{ $c->subject }}</span>
                                    <span class="block text-[12px] text-gray-400 mt-0.5 truncate">
                                        {{ $c->audience_tag ? 'Tagged #'.$c->audience_tag : 'All subscribers' }} ·
                                        @switch($c->status)
                                            @case('sent') sent {{ $c->sent_at?->format('j M Y') }} @break
                                            @case('scheduled') for {{ $c->scheduled_at?->format('j M, H:i') }} @break
                                            @case('sending') sending now @break
                                            @default edited {{ $c->updated_at->diffForHumans() }}
                                        @endswitch
                                    </span>
                                </span>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass($c->status) }}">{{ ucfirst($c->status) }}</span>
                            </div>
                            @if ($c->preheader)<p class="mt-2 text-[13px] text-gray-500 dark:text-gray-400 line-clamp-2">{{ $c->preheader }}</p>@endif
                            @if (in_array($c->status, ['sent', 'sending'], true))
                                <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                                    @foreach ([['Sent', number_format($c->sent_count)], ['Opened', $c->openRate().'%'], ['Clicked', $c->clickRate().'%'], ['Unsubs', number_format($c->unsubscribes_count)]] as [$l, $v])
                                        <span class="rounded-xl bg-gray-50 dark:bg-white/[0.04] py-2">
                                            <span class="block text-[15px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $v }}</span>
                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ $l }}</span>
                                        </span>
                                    @endforeach
                                </div>
                                @if ($c->status === 'sending')
                                    @php $done = $c->recipients_count ? round(($c->sent_count + $c->failed_count) / $c->recipients_count * 100) : 0; @endphp
                                    <span class="mt-3 block h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden"><span class="block h-full rounded-full bg-sky-500" style="width:{{ $done }}%"></span></span>
                                @endif
                                @if ($c->failed_count)
                                    <p class="mt-2 text-[12px] font-semibold text-rose-600 dark:text-rose-300">{{ $c->failed_count }} failed {{ Str::plural('send', $c->failed_count) }}</p>
                                @endif
                            @endif
                            @if ($c->error && $c->status === 'draft')
                                <p class="mt-2 text-[12px] text-rose-600 dark:text-rose-300">{{ $c->error }}</p>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                                @if ($c->isEditable())
                                    <button type="button" wire:click="editCampaign('{{ $c->id }}')" class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Edit</button>
                                @endif
                                @if ($c->status === 'scheduled')
                                    <button type="button" wire:click="unschedule('{{ $c->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Unschedule</button>
                                @endif
                                <button type="button" wire:click="duplicateCampaign('{{ $c->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Duplicate</button>
                                @if ($c->status !== 'sending')
                                    <button type="button" wire:click="deleteCampaign('{{ $c->id }}')" data-confirm="Delete the campaign “{{ $c->subject }}”{{ $c->status === 'sent' ? ' and its stats' : '' }}?" title="Delete"
                                            class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endif
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
                                <th class="px-4 py-3">Campaign</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Sent</th>
                                @unless ($compact)<th class="px-4 py-3 text-right">Opened</th><th class="px-4 py-3 text-right">Clicked</th>@endunless
                                <th class="w-28 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($campaigns as $c)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="crow-{{ $c->id }}">
                                    <td class="{{ $pad }}">
                                        <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[18rem]">{{ $c->subject }}</span>
                                        @unless ($compact)<span class="block text-[11px] text-gray-400">{{ $c->audience_tag ? '#'.$c->audience_tag : 'All subscribers' }}</span>@endunless
                                    </td>
                                    <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass($c->status) }}">{{ ucfirst($c->status) }}</span></td>
                                    <td class="{{ $pad }} text-right tabular-nums">{{ number_format($c->sent_count) }}</td>
                                    @unless ($compact)
                                        <td class="{{ $pad }} text-right tabular-nums">{{ $c->openRate() }}%</td>
                                        <td class="{{ $pad }} text-right tabular-nums">{{ $c->clickRate() }}%</td>
                                    @endunless
                                    <td class="{{ $pad }} text-right whitespace-nowrap">
                                        @if ($canManage)
                                            @if ($c->isEditable())<button type="button" wire:click="editCampaign('{{ $c->id }}')" class="text-[12px] font-semibold" style="color:var(--primary)">Edit</button>@endif
                                            @if ($c->status !== 'sending')
                                                <button type="button" wire:click="deleteCampaign('{{ $c->id }}')" data-confirm="Delete the campaign “{{ $c->subject }}”?" class="ml-2 text-[12px] font-semibold text-rose-500 hover:text-rose-600">Delete</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
    @endunless

    {{-- ═════════ Campaign editor: its own panel ═════════ --}}
    @if ($editing)
    <x-page-panel close="closeEditor" back-label="Back to campaigns" :title="$campaignId ? 'Edit campaign' : 'New campaign'"
        :subtitle="$editingCampaign?->status === 'scheduled' ? 'Scheduled for '.$editingCampaign->scheduled_at?->format('j M Y, H:i') : 'Write it, test it, then send or schedule it'">
        <div class="space-y-5">
            @if ($limitError)
                <div class="rounded-2xl px-4 py-3 bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-800 dark:text-rose-200">
                    <p class="font-bold">Monthly send limit reached</p>
                    <p class="mt-0.5">{{ $limitError }}</p>
                    <a href="{{ $upgradeUrl }}" wire:navigate class="inline-flex mt-2 px-3 py-1.5 rounded-lg text-[12px] font-bold bg-white dark:bg-[#1d1e2a] text-rose-700 dark:text-rose-200 border border-rose-200 dark:border-rose-500/30">See plans & upgrade →</a>
                </div>
            @endif
            @error('send') <div class="rounded-2xl px-4 py-3 bg-amber-50 dark:bg-amber-500/10 text-sm text-amber-800 dark:text-amber-200">{{ $message }}</div> @enderror

            <div>
                <x-field.text label="Subject" model="subject" placeholder="What's this email about?" />
                @error('subject') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field.text label="Preview text (optional)" model="preheader" placeholder="The line inboxes show after the subject" />
                @error('preheader') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 dark:text-gray-300 mb-1">Send to</label>
                <select wire:model.live="audienceTag" class="bkf-input">
                    <option value="">All subscribers ({{ number_format($stats['subscribed']) }})</option>
                    @foreach ($tags as $t)
                        <option value="{{ $t }}">Subscribers tagged #{{ $t }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                    {{ number_format($audienceCount ?? 0) }} {{ Str::plural('recipient', $audienceCount ?? 0) }} ·
                    {{ $quota['left'] === null ? 'unlimited sends on your plan' : number_format($quota['left']).' sends left this month' }}
                </p>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-gray-600 dark:text-gray-300">Content</span>
                <button type="button" wire:click="togglePreview" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">{{ $previewing ? 'Back to editing' : 'Preview email' }}</button>
            </div>
            <div @class(['hidden' => $previewing])>
                <div wire:ignore wire:key="nl-editor-{{ $editorKey }}">
                    @include('partials.post-body-editor', ['mediaAssets' => $this->mediaAssets])
                </div>
            </div>
            @if ($previewing)
                <iframe srcdoc="{{ $this->previewHtml }}" title="Email preview" sandbox class="w-full h-[36rem] rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white"></iframe>
            @endif

            <div class="rounded-2xl p-4 bg-gray-50 dark:bg-white/[0.03] space-y-3">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[12rem]">
                        <x-field.text label="Send a test to" model="testEmail" type="email" />
                        @error('testEmail') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="sendTest" wire:loading.attr="disabled" class="{{ $btnSolid }} text-sm px-4 py-2.5">Send test</button>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[12rem]">
                        <x-field.text label="Schedule for ({{ config('app.timezone') }})" model="scheduleAt" type="datetime-local" />
                        @error('scheduleAt') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="schedule" class="{{ $btnSolid }} text-sm px-4 py-2.5">Schedule</button>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-1">
                <button type="button" wire:click="closeEditor" class="{{ $btnSolid }} px-4 py-2.5 text-sm">Cancel</button>
                <button type="button" wire:click="saveDraft" class="{{ $btnSolid }} px-4 py-2.5 text-sm">Save draft</button>
                <button type="button" wire:click="sendNow" data-confirm="Send this campaign to {{ number_format($audienceCount ?? 0) }} {{ Str::plural('subscriber', $audienceCount ?? 0) }} now?"
                        class="flex-1 px-4 py-2.5 text-sm font-bold rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    Send now
                </button>
            </div>
        </div>
    </x-page-panel>
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · recent · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">List health</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</b> {{ Str::plural('address', $stats['total']) }} ·
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['subscribed']) }}</b> receiving
            </p>
            @if ($stats['total'])
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach (['subscribed', 'pending', 'unsubscribed', 'bounced'] as $k)
                        @if ($stats[$k])<span style="width:{{ round($stats[$k] / $stats['total'] * 100, 2) }}%;background:{{ $healthColor[$k] }}" title="{{ ucfirst($k) }} · {{ $stats[$k] }}"></span>@endif
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach (['subscribed', 'pending', 'unsubscribed', 'bounced'] as $k)
                        <button type="button" x-on:click="$wire.setTab('subscribers').then(() => $wire.setStatus('{{ $k }}'))" class="w-full flex items-center gap-2 text-[12.5px]">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $healthColor[$k] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($k) }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ number_format($stats[$k]) }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-[12px] text-gray-500 dark:text-gray-400">Add a newsletter signup to your site and new subscribers appear here.</p>
            @endif
        </div>

        @if ($stats['pending'] || $stats['drafts'] || $failedCampaigns->isNotEmpty() || $erroredCampaigns->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($stats['pending'])
                    <button type="button" x-on:click="$wire.setTab('subscribers').then(() => $wire.setStatus('pending'))" class="w-full text-left block rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ number_format($stats['pending']) }} pending {{ Str::plural('confirmation', $stats['pending']) }}</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">They haven't clicked the confirmation link yet →</p>
                    </button>
                @endif
                @foreach ($erroredCampaigns as $c)
                    <button type="button" wire:click="editCampaign('{{ $c->id }}')" class="w-full text-left block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200 truncate">“{{ $c->subject }}” didn't send</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 line-clamp-2">{{ $c->error }}</p>
                    </button>
                @endforeach
                @foreach ($failedCampaigns as $c)
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $c->failed_count }} failed {{ Str::plural('send', $c->failed_count) }}</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 truncate">in “{{ $c->subject }}”</p>
                    </div>
                @endforeach
                @if ($stats['drafts'])
                    <button type="button" x-on:click="$wire.setTab('campaigns').then(() => $wire.setCampaignFilter('draft'))" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['drafts'] }} {{ Str::plural('draft', $stats['drafts']) }} not sent</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Finish and send or schedule them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if ($recentCampaigns->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recent campaigns</p>
            <div class="space-y-2.5">
                @foreach ($recentCampaigns as $c)
                    <div class="flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ ['sent' => '#10b981', 'scheduled' => '#f59e0b', 'sending' => '#0ea5e9'][$c->status] ?? '#9ca3af' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $c->subject }}</span>
                            <span class="block text-[11px] text-gray-400">{{ ucfirst($c->status) }}@if ($c->status === 'sent') · {{ $c->openRate() }}% opened @endif</span>
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
                    ['Contacts', 'Everyone you know', route('site.contacts', $site->name)],
                    ['Forms', 'Signup & contact forms', route('site.forms', $site->name)],
                    ['Edit site', 'Place a signup box', route('site.connect', $site->name)],
                    ['Site Properties', 'Logo, email, address', route('site.properties', $site->name)],
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
@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endassets

@script
<script>
    /**
     * Same contract as the Posts editor (partials.post-body-editor → postQuill):
     * Quill writes HTML to $wire.body; the 🖼 button opens the Media lightbox.
     * Defined here too so the page works on a direct load; a version already
     * registered by the Posts page is compatible and kept.
     */
    if (!window.postQuill) window.postQuill = function ($wire, assets = []) {
        return {
            picker: false,
            pickerSearch: '',
            assets,
            savedIndex: null,
            quill() { return this.$refs.editor.__quill; },
            init() {
                if (!window.Quill) { setTimeout(() => this.init(), 100); return; }
                const q = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: 'Write your update…',
                    modules: {
                        toolbar: {
                            container: [
                                [{ header: 1 }, { header: 2 }, { header: 3 }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['blockquote', 'link'],
                                ['image'],
                                ['clean'],
                            ],
                            handlers: { image: () => this.openPicker() },
                        },
                    },
                });
                this.$refs.editor.__quill = q;
                const html = ($wire.body || '').trim();
                if (html) q.clipboard.dangerouslyPasteHTML(html, 'silent');
                $wire.body = q.root.innerHTML;
                q.on('text-change', () => { $wire.body = q.root.innerHTML; });
            },
            openPicker() {
                const q = this.quill();
                let sel = null;
                try { sel = q.getSelection(); } catch (_) {}
                this.savedIndex = sel ? sel.index : Math.max(0, q.getLength() - 1);
                this.pickerSearch = '';
                this.picker = true;
            },
            filteredAssets() {
                const s = this.pickerSearch.toLowerCase().trim();
                return s ? this.assets.filter(a => (a.name || '').toLowerCase().includes(s)) : this.assets;
            },
            insertAsset(a) {
                this.picker = false;
                const q = this.quill();
                const i = this.savedIndex ?? Math.max(0, q.getLength() - 1);
                q.insertEmbed(i, a.type === 'image' ? 'image' : 'video', a.url, 'api');
                try { q.setSelection(i + 1, 0, 'silent'); } catch (_) {}
                $wire.body = q.root.innerHTML;
            },
        };
    };
</script>
@endscript
