@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $input = 'w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[color:var(--primary)]/40 focus:border-[color:var(--primary)]';
    $ghost = 'fx inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400';
    $solid = 'fx inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold shadow-sm';
    $atLimit = $used >= $limit;
    $statusStyle = [
        'active' => ['Active', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
        'pending' => ['Setting up…', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'pending_dns' => ['Switching on…', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'verifying' => ['Waiting for DNS', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'suspended' => ['Suspended', 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'],
        'deleting' => ['Deleting…', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300'],
        'failed' => ['Failed', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300'],
    ];
    $badge = fn ($s) => '<span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full '.($statusStyle[$s][1] ?? $statusStyle['pending'][1]).'">'.e($statusStyle[$s][0] ?? ucfirst($s)).'</span>';
    $usedMb = $mailboxes->sum('quota_used_mb');
    $aliasCount = $mailboxes->sum(fn ($m) => $m->aliases->count());
    $suspended = $d && $d->isSuspended();
    $conn = config('email.connection');
    $tabs = ['mailboxes' => 'Mailboxes', 'dns' => 'Domain & DNS', 'setup' => 'Set up your apps'];
@endphp
<div @if ($busy) wire:poll.6s @endif>
<x-tri-layout title="Business email" :subtitle="$site->domain ? 'Mailboxes on '.$site->domain : 'Mailboxes on your own domain'"
    :site-name="$site->name" :labels="['📊 Overview', '✉️ Email', 'ℹ️ Help']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    {{-- ══ LEFT rail ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$used.' of '.$limit" label="Mailboxes used" :sub="$atLimit ? 'at your limit' : ($limit - $used).' left'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        <x-tile accent="lime" :value="$d ? ($statusStyle[$d->status][0] ?? ucfirst($d->status)) : 'Off'" label="Email status" :sub="$d?->domain ?? 'not set up'"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="sky" :value="$aliasCount" label="Aliases" sub="free, unlimited"
                icon="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
        <x-tile accent="lavender" :value="$usedMb >= 1024 ? round($usedMb / 1024, 1).' GB' : (int) $usedMb.' MB'" label="Storage used" :sub="config('email.quota_gb').' GB each'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
        <x-tile accent="cocoa" wide :value="$sub?->tier()['name'] ?? '—'" label="Your plan" :sub="$sub && $sub->extra_mailboxes ? '+'.$sub->extra_mailboxes.' extra mailboxes' : 'mailboxes included'"
                :href="route('account.subscription')"
                icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER ══ --}}
    <div class="@container max-w-[52rem] mx-auto space-y-4">

        {{-- Not possible yet --}}
        @if ($ineligible)
            <div class="{{ $panel }} p-8 text-center">
                <p class="text-4xl" aria-hidden="true">✉️</p>
                <h2 class="font-display text-xl font-extrabold text-gray-900 dark:text-white mt-2">Business email isn't available yet</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 max-w-md mx-auto">{{ $ineligible }}</p>
                <div class="flex flex-wrap justify-center gap-2 mt-5">
                    <a href="{{ url($site->name.'/publish') }}" class="{{ $ghost }}">Go live & domains</a>
                    <a href="{{ route('account.subscription') }}" class="{{ $solid }}" style="background:var(--primary);color:var(--on-primary)">See plans</a>
                </div>
            </div>

        {{-- Can be switched on --}}
        @elseif (! $d)
            <div class="{{ $panel }} p-7">
                <h2 class="font-display text-xl font-extrabold text-gray-900 dark:text-white">Get email at {{ $site->domain }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Addresses like <b>{{ 'hello@'.$site->domain }}</b> with {{ config('email.quota_gb') }} GB each, on your phone, laptop and in webmail. Your plan includes {{ $limit }} {{ \Illuminate\Support\Str::plural('mailbox', $limit) }}; aliases are free.</p>

                @if ($foreignMx)
                    <div class="mt-4 rounded-2xl p-4 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                        <p class="text-sm font-bold text-amber-900 dark:text-amber-200">⚠️ {{ $site->domain }} already receives email somewhere else</p>
                        <p class="text-[13px] text-amber-800 dark:text-amber-200/90 mt-1">It's currently delivered to <b>{{ implode(', ', $foreignMx) }}</b>. Switching means <b>new email will stop arriving at your old provider</b> and go to your new mailboxes instead. Move or export anything you need first — this can't be undone by us.</p>
                        <label class="flex items-start gap-2 mt-3 text-[13px] font-semibold text-amber-900 dark:text-amber-100 cursor-pointer">
                            <input type="checkbox" wire:model.live="confirmMx" class="mt-0.5 rounded">
                            I understand existing email will stop arriving at my old provider.
                        </label>
                    </div>
                @endif
                @error('confirm')<p class="text-xs text-rose-500 mt-2">{{ $message }}</p>@enderror
                @error('domain')<p class="text-xs text-rose-500 mt-2">{{ $message }}</p>@enderror

                <button wire:click="enable" wire:loading.attr="disabled" @disabled($foreignMx && ! $confirmMx)
                        class="{{ $solid }} mt-5 disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">
                    <span wire:loading.remove wire:target="enable">Switch on business email</span>
                    <span wire:loading wire:target="enable">Switching on…</span>
                </button>
            </div>

        {{-- Set up --}}
        @else
            @if ($suspended)
                <div class="rounded-2xl px-5 py-4 bg-gray-900 text-white">
                    <p class="font-bold">Business email is suspended</p>
                    <p class="text-[13px] opacity-85 mt-0.5">Your subscription has ended, so mailboxes can't be changed. They keep working and keep their mail until <b>{{ $d->delete_after?->toFormattedDateString() }}</b> — export what you need, or <a href="{{ route('account.subscription') }}" class="underline font-bold">pick a plan</a> to keep them.</p>
                </div>
            @endif

            <div x-data="{ tab: (location.hash || '#mailboxes').slice(1) }"
                 x-init="if (! @js(array_keys($tabs)).includes(tab)) tab = 'mailboxes'; $watch('tab', t => history.replaceState(null, '', '#' + t))">
                <x-pill-tabs :tabs="$tabs" :dots="$d->status === 'active' ? [] : ['dns']" />

                {{-- ── Mailboxes ── --}}
                <section x-show="tab === 'mailboxes'" class="space-y-4">
                    <div class="{{ $panel }} p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $used }} of {{ $limit }} mailboxes used</p>
                                <div class="mt-1.5 h-1.5 w-56 max-w-full rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                                    <div class="h-full rounded-full" style="width:{{ $limit ? min(100, round($used / $limit * 100)) : 100 }}%;background:{{ $atLimit ? '#f43f5e' : 'var(--primary)' }}"></div>
                                </div>
                            </div>
                            @unless ($suspended)
                                <button wire:click="$toggle('creating')" @disabled($atLimit || $d->status !== 'active')
                                        class="{{ $solid }} disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">+ New mailbox</button>
                            @endunless
                        </div>
                        @if ($d->status !== 'active' && ! $suspended)
                            <p class="text-[12.5px] text-amber-700 dark:text-amber-300 mt-3">You can create mailboxes once your domain's email records are confirmed — see <button type="button" @click="tab = 'dns'" class="underline font-bold">Domain & DNS</button>.</p>
                        @endif
                        @if ($atLimit && ! $suspended)
                            <div class="mt-3 rounded-xl p-3 bg-gray-50 dark:bg-white/[0.03] flex flex-wrap items-center gap-3">
                                <p class="text-[13px] text-gray-700 dark:text-gray-200 flex-1 min-w-[200px]">You've used every mailbox on your plan. Aliases (extra addresses into an existing mailbox) are free and unlimited.</p>
                                @if ($addonAvailable)
                                    <select wire:model="addonQty" class="{{ $input }} !w-20">
                                        @foreach ([1, 2, 3, 5, 10] as $q)<option value="{{ $q }}">+{{ $q }}</option>@endforeach
                                    </select>
                                    <button wire:click="buyAddon" data-confirm="Add {{ $addonQty }} extra mailbox(es) at {{ \App\Support\Money::format((int) config('email.addon.price_cents'), 'gbp') }} each per month?" class="{{ $ghost }}">Add mailboxes</button>
                                @endif
                                <a href="{{ route('account.subscription') }}" class="{{ $solid }}" style="background:var(--foreground);color:var(--background)">Upgrade plan</a>
                            </div>
                            @error('quantity')<p class="text-xs text-rose-500 mt-2">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    @if ($creating && ! $suspended)
                        <div class="{{ $panel }} p-6">
                            <h2 class="text-[16px] font-bold text-gray-900 dark:text-white mb-4">New mailbox</h2>
                            <div class="grid @xl:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1">Address</label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" wire:model.blur="localPart" class="{{ $input }}" placeholder="hello" maxlength="64" autocomplete="off"
                                               x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')">
                                        <span class="text-sm font-semibold text-gray-500 shrink-0">{{ '@'.$d->domain }}</span>
                                    </div>
                                    @error('local_part')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                                    @error('limit')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1">Display name</label>
                                    <input type="text" wire:model.blur="displayName" class="{{ $input }}" placeholder="Grace Way Church" maxlength="120">
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1.5">Password</p>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="passwordMode" value="generate"> Generate a strong one for me</label>
                                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="passwordMode" value="own"> I'll choose one</label>
                                </div>
                                @if ($passwordMode === 'own')
                                    <input type="password" wire:model.blur="ownPassword" class="{{ $input }} mt-2 max-w-sm" autocomplete="new-password" placeholder="At least 12 characters">
                                @endif
                                @error('password')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                                <p class="text-[11px] text-gray-400 mt-1.5">We never store mailbox passwords. You'll see it once — then you can only reset it.</p>
                            </div>
                            <div class="flex justify-end gap-2 mt-5">
                                <button type="button" wire:click="$set('creating', false)" class="{{ $ghost }}">Cancel</button>
                                <button type="button" wire:click="createMailbox" wire:loading.attr="disabled" class="{{ $solid }}" style="background:var(--primary);color:var(--on-primary)">Create mailbox</button>
                            </div>
                        </div>
                    @endif

                    @forelse ($mailboxes as $m)
                        <div class="{{ $panel }} p-5" wire:key="mb-{{ $m->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[15px] font-bold text-gray-900 dark:text-white break-all">{{ $m->local_part.'@'.$d->domain }}</p>
                                    <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $m->display_name ?: 'No display name' }}
                                        @if ($m->quota_used_mb !== null) · {{ $m->quota_used_mb >= 1024 ? round($m->quota_used_mb / 1024, 1).' GB' : $m->quota_used_mb.' MB' }} of {{ $m->quota_gb }} GB @endif
                                    </p>
                                </div>
                                {!! $badge($m->status) !!}
                            </div>
                            @if ($m->error)<p class="text-[12px] text-rose-500 mt-2">{{ $m->error }}</p>@endif

                            <div class="flex flex-wrap items-center gap-1.5 mt-3">
                                @foreach ($m->aliases as $a)
                                    <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 rounded-full text-[11.5px] font-semibold bg-gray-100 dark:bg-white/[0.07] text-gray-700 dark:text-gray-200" wire:key="al-{{ $a->id }}">
                                        {{ $a->local_part }}&#64; @if ($a->status !== 'active')<span class="text-[10px] opacity-60">({{ $a->status }})</span>@endif
                                        @if (! $suspended && $a->status === 'active')
                                            <button type="button" wire:click="removeAlias('{{ $a->id }}')" data-confirm="Remove the alias {{ $a->local_part.'@'.$d->domain }}? Mail to it will bounce." class="w-5 h-5 grid place-items-center rounded-full hover:bg-rose-100 hover:text-rose-600" aria-label="Remove alias">×</button>
                                        @endif
                                    </span>
                                @endforeach
                            </div>

                            @if (! $suspended && $m->status === 'active')
                                <div x-data="{ open: false }" class="mt-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="open = ! open" class="{{ $ghost }}">Manage</button>
                                        <button type="button" wire:click="resetPassword('{{ $m->id }}')" data-confirm="Set a new password for {{ $m->address() }}? Apps using the old one will need the new password." class="{{ $ghost }}">Reset password</button>
                                        <button type="button" wire:click="askDelete('{{ $m->id }}')" class="{{ $ghost }} !text-rose-600 hover:!border-rose-300">Delete</button>
                                    </div>
                                    <div x-show="open" x-cloak class="grid @xl:grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1">Display name</label>
                                            <div class="flex gap-2">
                                                <input type="text" wire:model="names.{{ $m->id }}" class="{{ $input }}" placeholder="{{ $m->display_name }}">
                                                <button type="button" wire:click="saveName('{{ $m->id }}')" class="{{ $ghost }}">Save</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1">Add an alias</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" wire:model="aliasInput.{{ $m->id }}" class="{{ $input }}" placeholder="bookings"
                                                       x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')">
                                                <button type="button" wire:click="addAlias('{{ $m->id }}')" class="{{ $ghost }}">Add</button>
                                            </div>
                                            @error('alias')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="{{ $panel }} p-8 text-center">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No mailboxes yet</p>
                            <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Create one when your domain is ready — they're set up one at a time, as you need them.</p>
                        </div>
                    @endforelse
                </section>

                {{-- ── Domain & DNS ── --}}
                <section x-show="tab === 'dns'" x-cloak class="space-y-4">
                    <div class="{{ $panel }} p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">{{ $d->domain }} {!! $badge($d->status) !!}</h2>
                                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">
                                    @if ($d->source === 'openprovider')
                                        Your domain is with us, so we add these records for you. They can take up to an hour to show everywhere.
                                    @else
                                        Add these records where your domain's DNS is managed (your registrar), exactly as shown. Keep just <b>one</b> SPF record — ours below already includes any you had.
                                    @endif
                                </p>
                                @if ($d->dns_checked_at)<p class="text-[11px] text-gray-400 mt-1">Last checked {{ $d->dns_checked_at->diffForHumans() }}</p>@endif
                            </div>
                            @unless ($suspended)
                                <button type="button" wire:click="recheck" wire:loading.attr="disabled" class="{{ $ghost }}">Check again</button>
                            @endunless
                        </div>
                        @if ($d->error)<p class="text-[12.5px] text-rose-500 mt-3">{{ $d->error }}</p>@endif
                    </div>

                    <div class="{{ $panel }} overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="text-left text-[11px] uppercase tracking-wider text-gray-400">
                                    <tr><th class="px-4 py-3">Purpose</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Name / host</th><th class="px-4 py-3">Value</th><th class="px-4 py-3"></th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                                    @foreach ($records as $r)
                                        @php $host = $r['name'] === '' ? '@' : $r['name']; $value = ($r['prio'] ?? null) !== null && $r['type'] === 'MX' ? $r['prio'].' '.$r['value'] : $r['value']; @endphp
                                        <tr class="align-top">
                                            <td class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                                @isset($r['ok'])<span class="{{ $r['ok'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $r['ok'] ? '✓' : '•' }}</span>@endisset
                                                {{ $r['purpose'] }}
                                            </td>
                                            <td class="px-4 py-3 font-mono">{{ $r['type'] }}</td>
                                            <td class="px-4 py-3 font-mono break-all">{{ $host }}</td>
                                            <td class="px-4 py-3 font-mono break-all max-w-[22rem]">{{ $value }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap" x-data="{ copied: false }">
                                                @if ($d->source === 'byo')
                                                    <button type="button" class="{{ $ghost }}" @click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy value'"></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                {{-- ── Setup help ── --}}
                <section x-show="tab === 'setup'" x-cloak class="space-y-4">
                    <div class="{{ $panel }} p-6">
                        <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">Connection settings</h2>
                        @if (filled($conn['imap']['host']) && filled($conn['smtp']['host']))
                            <div class="grid @xl:grid-cols-2 gap-3 mt-3">
                                @foreach (['imap' => 'Incoming (IMAP)', 'smtp' => 'Outgoing (SMTP)', 'pop3' => 'Incoming (POP3)'] as $k => $l)
                                    @continue(! filled($conn[$k]['host']))
                                    <div class="rounded-2xl p-4 bg-gray-50 dark:bg-white/[0.03]">
                                        <p class="text-[12px] font-bold text-gray-600 dark:text-gray-300">{{ $l }}</p>
                                        <p class="font-mono text-sm text-gray-900 dark:text-white mt-1">{{ $conn[$k]['host'] }}</p>
                                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Port {{ $conn[$k]['port'] }} · {{ $conn[$k]['security'] }}</p>
                                    </div>
                                @endforeach
                                <div class="rounded-2xl p-4 bg-gray-50 dark:bg-white/[0.03]">
                                    <p class="text-[12px] font-bold text-gray-600 dark:text-gray-300">Username</p>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $conn['username'] }}</p>
                                    @if (filled($conn['webmail']))<a href="{{ $conn['webmail'] }}" target="_blank" rel="noopener" class="text-[12px] font-bold" style="color:var(--primary)">Open webmail →</a>@endif
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Server settings will appear here shortly. Most mail apps also find them automatically when you enter your email address and password.</p>
                        @endif
                    </div>
                    <div class="grid @xl:grid-cols-2 gap-4">
                        @foreach ([
                            ['iPhone / iPad', ['Settings › Mail › Accounts › Add Account › Other.', 'Choose "Add Mail Account", enter your name, full email address and password.', 'Pick IMAP and enter the incoming and outgoing servers above if asked.']],
                            ['Android (Gmail app)', ['Open Gmail › your profile picture › Add another account › Other.', 'Enter your full email address, then "Personal (IMAP)".', 'Enter your password and the servers above if asked.']],
                            ['Outlook', ['File › Add Account, enter your email address.', 'Choose "Let me set up my account manually" › IMAP if it doesn\'t connect by itself.', 'Use the servers above; your username is the full address.']],
                            ['Webmail', ['Open webmail in any browser.', 'Sign in with your full email address and password.', 'Handy when you\'re away from your own devices.']],
                        ] as [$title, $steps])
                            <div class="{{ $panel }} p-5">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $title }}</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1 text-[12.5px] text-gray-600 dark:text-gray-300">
                                    @foreach ($steps as $s)<li>{{ $s }}</li>@endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @endif
    </div>

    {{-- ══ RIGHT rail ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-2" style="color:var(--primary)">Good to know</p>
            <ul class="space-y-2 text-[12.5px] text-gray-600 dark:text-gray-300">
                <li>📦 {{ config('email.quota_gb') }} GB storage per mailbox.</li>
                <li>🔗 Aliases are free and unlimited — they don't count toward your mailboxes.</li>
                <li>📤 Up to {{ number_format(config('email.sending_limits.per_mailbox_day')) }} emails a day per mailbox, {{ number_format(config('email.sending_limits.per_domain_day')) }} per domain.</li>
                <li>🔒 We never store mailbox passwords — you'll see a new one once, then only reset it.</li>
                <li>🧾 Booking and invoice emails still come from us, with replies going to your address.</li>
            </ul>
        </div>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['Go live & domains', 'your domain and its records', url($site->name.'/publish')],
                ['Plans', 'mailboxes per plan', route('account.subscription')],
                ['Site properties', 'reply-to and sender name', url($site->name.'/properties#seo')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
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

{{-- New password, shown ONCE --}}
@if ($shownPassword)
    <div class="fixed inset-0 z-[60] grid place-items-center p-4" x-data="{ copied: false }">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-[#1d1e2a] p-6 shadow-2xl">
            <h3 class="font-display text-lg font-extrabold text-gray-900 dark:text-white">Save this password now</h3>
            <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">For <b>{{ $shownPassword['address'] }}</b>. We don't keep a copy — once you close this, you can only set a new one.</p>
            <div class="mt-4 flex items-center gap-2 rounded-xl bg-gray-50 dark:bg-white/[0.05] p-3">
                <code class="flex-1 font-mono text-[15px] break-all text-gray-900 dark:text-white select-all">{{ $shownPassword['password'] }}</code>
                <button type="button" class="{{ $ghost }}" @click="navigator.clipboard.writeText(@js($shownPassword['password'])); copied = true" x-text="copied ? 'Copied' : 'Copy'"></button>
            </div>
            <p class="text-[11.5px] text-gray-400 mt-2">The mailbox is being set up — it usually takes under a minute.</p>
            <div class="flex justify-end mt-5">
                <button type="button" wire:click="dismissPassword" class="{{ $solid }}" style="background:var(--primary);color:var(--on-primary)">I've saved it</button>
            </div>
        </div>
    </div>
@endif

{{-- Delete: type the address to confirm --}}
@if ($deleteId && ($dm = $mailboxes->firstWhere('id', $deleteId)))
    <div class="fixed inset-0 z-[60] grid place-items-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('deleteId', null)"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-[#1d1e2a] p-6 shadow-2xl">
            <h3 class="font-display text-lg font-extrabold text-rose-600">Delete {{ $dm->address() }}?</h3>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mt-1"><b>Every email in this mailbox is permanently deleted</b>, along with its aliases. This can't be undone.</p>
            <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mt-4 mb-1">Type <span class="font-mono">{{ $dm->address() }}</span> to confirm</label>
            <input type="text" wire:model.live="deleteTyped" class="{{ $input }}" autocomplete="off" autofocus>
            @error('confirm_address')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" wire:click="$set('deleteId', null)" class="{{ $ghost }}">Cancel</button>
                <button type="button" wire:click="confirmDelete" @disabled(strtolower(trim($deleteTyped)) !== strtolower($dm->address()))
                        class="{{ $solid }} bg-rose-600 text-white disabled:opacity-40">Delete forever</button>
            </div>
        </div>
    </div>
@endif
</div>
