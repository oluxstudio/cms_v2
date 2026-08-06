<div class="h-full overflow-y-auto p-5 sm:p-6" wire:key="invoices-{{ $site->id }}">

    @php
        $s = $this->stats; $bal = $this->balance; $hero = $this->hero;
        $accent = 'var(--primary)'; // APP theme token (orange light / blue dark)
        $delta = fn (?int $d) => $d === null ? null : (($d >= 0 ? '▲ ' : '▼ ').abs($d).'% vs last month');
        $donutColors = [$accent, '#f59e0b', '#10b981', '#ec4899'];
    @endphp

    {{-- ══ Hero greeting ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                Hello, {{ ucfirst(explode(' ', trim($site->user?->name ?? 'there'))[0]) }}! 👋</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">This is what's happening with your billing this month.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-3 py-2 rounded-xl bg-white dark:bg-white/[0.03] border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300">{{ now()->format('F Y') }} ▾</span>
            <button type="button" wire:click="openForm"
                    class="text-xs font-bold px-4 py-2.5 rounded-xl text-white hover:opacity-90 shadow-md"
                    style="background:var(--primary); color:var(--on-primary); box-shadow:0 6px 16px -6px color-mix(in srgb, var(--primary) 55%, transparent)">＋ Create invoice</button>
        </div>
    </div>
    @unless($site->stripeReady())
        <p class="mb-5 text-xs px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-400">
            Stripe isn't connected — invoices can be sent but not paid online. Connect it in <a href="{{ url($site->name.'/marketplace') }}" class="font-semibold underline">Marketplace</a>.
        </p>
    @endunless

    {{-- ══ Row: stat tiles + revenue chart ══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mb-4">
        <div class="xl:col-span-2 grid grid-cols-2 gap-4">
            <x-tile accent="ink" :value="\App\Support\Money::format($bal['cents'], $site->currency)"
                    label="Total revenue this month" :sub="$delta($bal['delta']) ?? 'this month'" />
            <x-tile accent="lime" :value="$hero['invoices']"
                    label="Invoices sent this month" :sub="$delta($hero['invDelta']) ?? 'this month'" />
            <x-tile accent="lavender" :value="$hero['clients']"
                    label="Clients billed this month" :sub="$delta($hero['cliDelta']) ?? 'this month'" />
            <x-tile accent="cocoa" :value="$s['outstanding']"
                    label="Outstanding balance" :sub="$s['overdueN'] > 0 ? '⚠ '.$s['overdueN'].' overdue' : 'nothing overdue'" />
        </div>

        {{-- Revenue chart (hatched past months, accent current) --}}
        <div class="xl:col-span-3 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Revenue</h2>
                <span class="text-[11px] text-gray-400">last 6 months · all sources</span>
            </div>
            @php
                $months = $this->monthly;
                $max = max(1, collect($months)->max('cents'));
                $W = 440; $H = 130; $pad = 8; $bw = 46; $gap2 = ($W - 2 * $pad - 6 * $bw) / 5;
                $curKey = now()->format('Y-m');
                $fmtShort = fn (int $c) => config('currencies.'.strtolower($site->currency ?: 'gbp').'.position', 'before') === 'after'
                    ? number_format($c / 100, 0).' '.\App\Support\Money::symbol($site->currency)
                    : \App\Support\Money::symbol($site->currency).number_format($c / 100, 0);
            @endphp
            <svg viewBox="0 0 {{ $W }} {{ $H + 24 }}" class="w-full ivc" role="img" aria-label="Monthly revenue, last six months">
                <defs>
                    <pattern id="ivc-hatch" width="7" height="7" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
                        <rect width="7" height="7" class="ivc-hatch-bg"/>
                        <line x1="0" y1="0" x2="0" y2="7" class="ivc-hatch-line" stroke-width="2.5"/>
                    </pattern>
                </defs>
                @php $curM = collect($months)->firstWhere('key', $curKey); $curH = $curM ? max(4, (int) round(($curM['cents'] / $max) * ($H - 30))) : 0; @endphp
                @if($curM && $curM['cents'] > 0)
                    <line x1="{{ $pad }}" x2="{{ $W - $pad }}" y1="{{ $H - $curH }}" y2="{{ $H - $curH }}"
                          style="stroke:var(--primary)" stroke-width="1.5" stroke-dasharray="5 4" opacity=".65"/>
                @endif
                @foreach($months as $i => $m)
                    @php
                        $h = $m['cents'] > 0 ? max(4, (int) round(($m['cents'] / $max) * ($H - 30))) : 3;
                        $x = $pad + $i * ($bw + $gap2);
                        $isCur = $m['key'] === $curKey;
                    @endphp
                    <g class="ivc-bar">
                        <rect x="{{ $x - 3 }}" y="0" width="{{ $bw + 6 }}" height="{{ $H }}" fill="transparent"/>
                        <rect x="{{ $x }}" y="{{ $H - $h }}" width="{{ $bw }}" height="{{ $h }}" rx="7"
                              @if($isCur) style="fill:var(--primary)" @else fill="url(#ivc-hatch)" @endif class="{{ $isCur ? '' : 'ivc-ghost' }}"/>
                        @if($isCur)
                            <g>
                                <rect x="{{ $x + $bw / 2 - 34 }}" y="{{ max(2, $H - $h - 26) }}" width="68" height="19" rx="6" fill="#111827"/>
                                <text x="{{ $x + $bw / 2 }}" y="{{ max(2, $H - $h - 26) + 13 }}" text-anchor="middle" fill="#fff" font-size="10" font-weight="700">{{ $fmtShort($m['cents']) }}</text>
                            </g>
                        @else
                            <text x="{{ $x + $bw / 2 }}" y="{{ $H - $h - 7 }}" text-anchor="middle"
                                  class="ivc-val fill-gray-500 dark:fill-gray-300" font-size="10" font-weight="700">{{ $fmtShort($m['cents']) }}</text>
                        @endif
                        <text x="{{ $x + $bw / 2 }}" y="{{ $H + 16 }}" text-anchor="middle" font-size="10"
                              class="{{ $isCur ? 'font-bold' : '' }}" style="fill:{{ $isCur ? 'var(--primary)' : '#9ca3af' }}">{{ $m['label'] }}</text>
                    </g>
                @endforeach
            </svg>
        </div>
    </div>

    {{-- ══ Row: big numbers + revenue-by-source donut ══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <x-tile accent="sky" :value="$hero['allCount']" label="invoices all time"
                :sub="$hero['awaiting'] > 0 ? $hero['awaiting'].' awaiting payment' : 'everything settled — nice'">
            <span class="w-8 h-8 rounded-full grid place-items-center text-sm" style="background:#bfdcf7">🧾</span>
        </x-tile>
        <x-tile accent="lavender" :value="$hero['allClients']" label="clients all time"
                :sub="$hero['overdueN'] > 0 ? $hero['overdueN'].' overdue — may need chasing' : 'no one is behind on payments'">
            <span class="w-8 h-8 rounded-full grid place-items-center text-sm" style="background:#d7c3f5">👤</span>
        </x-tile>
        <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5">
            <div class="flex items-baseline justify-between mb-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Revenue by Source</h2>
                <span class="text-[11px] text-gray-400">all time</span>
            </div>
            @php $shares = $this->sourceShares; @endphp
            @if(count($shares))
                <div class="flex items-center gap-4">
                    @php $R = 34; $C = 2 * M_PI * $R; $off = 0; @endphp
                    <svg viewBox="0 0 90 90" class="w-24 h-24 shrink-0 -rotate-90">
                        @foreach($shares as $i => $c)
                            @php $len = $C * $c['share'] / 100; @endphp
                            <circle cx="45" cy="45" r="{{ $R }}" fill="none" style="stroke:{{ $donutColors[$i % 4] }}"
                                    stroke-width="13" stroke-dasharray="{{ max(0.1, $len - 1.5) }} {{ $C }}"
                                    stroke-dashoffset="{{ -$off }}"/>
                            @php $off += $len; @endphp
                        @endforeach
                    </svg>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        @foreach($shares as $i => $c)
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background:{{ $donutColors[$i % 4] }}"></span>
                                <span class="truncate text-gray-600 dark:text-gray-300 font-medium">{{ $c['name'] }}</span>
                                <span class="ml-auto shrink-0 font-bold text-gray-800 dark:text-gray-100 tabular-nums">{{ $c['share'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="py-8 text-center text-xs text-gray-400">No paid revenue yet.</p>
            @endif
        </div>
    </div>

    {{-- ══ Row: Email Reminders + Invoice Generator ══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
        <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Email Reminders</h2>
                <span class="text-[10px] text-gray-400">auto · hourly</span>
            </div>
            <div class="flex-1 overflow-y-auto max-h-[260px]">
                @forelse($this->reminderFeed as $r)
                    <button type="button" wire:click="viewInvoice({{ $r->id }})"
                            class="w-full text-left flex items-start gap-2.5 px-4 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                        <span class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-[11px] font-extrabold text-white"
                              style="background:{{ $accent }}">{{ strtoupper(mb_substr($r->customer_name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-gray-900 dark:text-white truncate">{{ $r->customer_name }}
                                @if($r->status === 'overdue')<span class="text-rose-500">· overdue</span>@endif</span>
                            <span class="block text-[10.5px] text-gray-400 truncate">{{ $r->customer_email }}</span>
                            <span class="block text-[10.5px] text-gray-400 mt-0.5">
                                {{ $r->number }} · {{ $r->formattedTotal() }} ·
                                @if($r->reminders_sent > 0) {{ $r->reminders_sent }} sent{{ $r->reminded_at ? ' · '.$r->reminded_at->diffForHumans(short: true) : '' }}
                                @else due {{ $r->due_date?->format('M j') }} @endif
                            </span>
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-10 text-center text-xs text-gray-400">Nothing needs a nudge — reminders send automatically near and past due dates.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 flex flex-col">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Invoice Generator</h2>
            <p class="text-[11px] text-gray-400 mb-2">Describe the invoice — client email + amounts — and a draft appears in the list.</p>
            <textarea wire:model="genPrompt" rows="3" maxlength="300"
                      placeholder="Logo design 250 and hosting 40, for Jane Doe jane@studio.com, due in 14 days"
                      class="bkf-input flex-1 resize-none text-xs"></textarea>
            <button type="button" wire:click="generateFromPrompt"
                    class="mt-2.5 w-full py-2.5 rounded-xl text-xs font-bold text-white transition-opacity hover:opacity-90"
                    style="background:{{ $accent }}">✨ Generate draft</button>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- ── New invoices (drafts, not yet sent) ── --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
                    <h2 class="text-sm font-bold">New invoices <span class="text-gray-400 font-normal">· not sent yet</span></h2>
                    <button type="button" wire:click="openForm" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg text-white"
                            style="background:{{ $accent }}">＋ New</button>
                </div>
                <div class="max-h-[420px] overflow-y-auto">
                    @forelse($this->drafts as $d)
                        <div class="flex items-center gap-2.5 px-4 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                            <div class="min-w-0 flex-1 cursor-pointer" wire:click="viewInvoice({{ $d->id }})">
                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $d->number }}
                                    <span class="text-gray-400 font-normal">· {{ $d->customer_name }}</span></p>
                                <p class="text-[10.5px] text-gray-400 truncate">{{ $d->customer_email }}
                                    @if($d->due_date) · due {{ $d->due_date->format('M j') }} @endif
                                    @if($d->isRecurring()) · ↻ {{ $d->recur_interval }} @endif</p>
                            </div>
                            <span class="shrink-0 text-xs font-extrabold tabular-nums">{{ $d->formattedTotal() }}</span>
                            <div class="shrink-0 flex items-center gap-1">
                                <button wire:click="sendInvoice({{ $d->id }})"
                                        data-confirm="Email invoice {{ $d->number }} with its pay link to {{ $d->customer_email }}?"
                                        class="text-[11px] px-2 py-1 rounded-lg font-bold text-white" style="background:{{ $accent }}">Send</button>
                                <button wire:click="editInvoice({{ $d->id }})" class="text-[11px] px-2 py-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-white/[0.06]" title="Edit">✎</button>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <p class="text-xs text-gray-400">No unsent invoices.</p>
                            <button type="button" wire:click="openForm" class="mt-3 text-[11px] font-bold px-3 py-1.5 rounded-lg text-white" style="background:{{ $accent }}">＋ Create one</button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        {{-- ── Invoice list ── --}}
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/[0.06] flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold mr-auto">All Invoices</h2>
                    <select wire:model.live="clientFilter" class="text-[11px] font-semibold rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] px-2 py-1.5 text-gray-600 dark:text-gray-300">
                        <option value="all">All clients</option>
                        @foreach($this->clients as $c)
                            <option value="{{ $c->customer_email }}">{{ $c->customer_name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" class="text-[11px] font-semibold rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] px-2 py-1.5 text-gray-600 dark:text-gray-300 capitalize">
                        @foreach(['all', 'draft', 'sent', 'paid', 'overdue', 'cancelled'] as $f)
                            <option value="{{ $f }}">{{ $f === 'all' ? 'All statuses' : ucfirst($f) }}</option>
                        @endforeach
                    </select>
                    <div class="relative w-full sm:w-auto">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-xs">⌕</span>
                        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search"
                               class="w-full sm:w-36 text-[11px] font-medium rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] pl-7 pr-2 py-1.5 text-gray-700 dark:text-gray-200">
                    </div>
                </div>
                @forelse($this->invoices as $inv)
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 dark:border-white/[0.04] last:border-0 hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors">
                        <div class="min-w-0 flex-1 cursor-pointer" wire:click="viewInvoice({{ $inv->id }})" title="View details">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $inv->number }} <span class="text-gray-400 font-normal">· {{ $inv->customer_name }}</span>
                            </p>
                            <p class="text-[11px] text-gray-400">
                                {{ $inv->customer_email }}
                                @if($inv->due_date) · due {{ $inv->due_date->format('M j, Y') }} @endif
                                @if($inv->paid_at) · paid {{ $inv->paid_at->format('M j, Y') }} @endif
                                @if($inv->isRecurring()) · <span class="text-indigo-500 font-semibold">↻ {{ $inv->recur_interval }}</span> @endif
                                @if($inv->parent_invoice_id) · <span class="text-gray-400">auto from {{ $inv->parent?->number }}</span> @endif
                                @if($inv->reminders_sent > 0) · {{ $inv->reminders_sent }} reminder(s) @endif
                            </p>
                        </div>
                        @if($inv->opened_at || $inv->viewed_at)
                            <span class="shrink-0 text-[10px] font-semibold text-sky-600 dark:text-sky-400"
                                  title="Opened {{ $inv->opened_at?->format('M j g:i A') ?? '—' }} · Viewed {{ $inv->viewed_at?->format('M j g:i A') ?? '—' }}">
                                {{ $inv->viewed_at ? '👁 viewed' : '✉ opened' }}</span>
                        @endif
                        <span class="shrink-0 text-sm font-extrabold tabular-nums">{{ $inv->formattedTotal() }}</span>
                        {{-- status: coloured dot + label, never colour alone --}}
                        <span class="shrink-0 inline-flex items-center gap-1.5 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full
                            {{ ['paid' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                                'sent' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                                'overdue' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
                                'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
                                'draft' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'][$inv->status] }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $inv->status }}
                        </span>
                        <div class="shrink-0 flex items-center gap-1">
                            @if(! in_array($inv->status, ['paid', 'cancelled'], true))
                                <button wire:click="sendInvoice({{ $inv->id }})"
                                        data-confirm="Email invoice {{ $inv->number }} with its pay link to {{ $inv->customer_email }}?"
                                        class="text-[11px] px-2 py-1 rounded-lg text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-500/10"
                                        title="Email the invoice with its pay link">{{ $inv->sent_at ? 'Resend' : 'Send' }}</button>
                                <button wire:click="markPaid({{ $inv->id }})"
                                        data-confirm="Mark {{ $inv->number }} as paid ({{ $inv->formattedTotal() }})?"
                                        class="text-[11px] px-2 py-1 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">Mark paid</button>
                            @endif
                            <a href="{{ $inv->payUrl() }}" target="_blank" rel="noopener"
                               class="text-[11px] px-2 py-1 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Open the public invoice page">View</a>
                            @if($inv->status === 'draft')
                                <button wire:click="editInvoice({{ $inv->id }})" class="text-gray-400 hover:text-indigo-600" title="Edit">✎</button>
                            @endif
                            @if(! in_array($inv->status, ['paid'], true))
                                <button wire:click="{{ $inv->status === 'cancelled' ? 'deleteInvoice' : 'cancelInvoice' }}({{ $inv->id }})"
                                        data-confirm="{{ $inv->status === 'cancelled' ? 'Delete invoice '.$inv->number.' permanently?' : 'Cancel invoice '.$inv->number.'? The pay link stops working.' }}"
                                        class="text-gray-300 hover:text-rose-500" title="{{ $inv->status === 'cancelled' ? 'Delete' : 'Cancel' }}">✕</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-12 text-center text-sm text-gray-400">
                        {{ $statusFilter === 'all' ? 'No invoices yet — create your first one on the left.' : 'No '.$statusFilter.' invoices.' }}
                    </p>
                @endforelse
                <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">{{ $this->invoices->links() }}</div>
            </div>
        </div>
    </div>

    <style>
        /* chart hover: value labels appear per-bar; the max bar is always labeled */
        .ivc .ivc-val { opacity: 0; transition: opacity .12s; }
        .ivc .ivc-val-on, .ivc .ivc-bar:hover .ivc-val { opacity: 1; }
        .ivc .ivc-bar:hover .ivc-fill { fill: #4f46e5; }
        /* hatched ghost bars (past months) — theme-aware */
        .ivc .ivc-hatch-bg { fill: #f1f2f6; }
        .ivc .ivc-hatch-line { stroke: #d8dae2; }
        .dark .ivc .ivc-hatch-bg { fill: rgba(255,255,255,.04); }
        .dark .ivc .ivc-hatch-line { stroke: rgba(255,255,255,.14); }
        .ivc .ivc-ghost { transition: opacity .12s; }
        .ivc .ivc-bar:hover .ivc-ghost { opacity: .75; }
    </style>

    {{-- ══════════ CREATE / EDIT — form lightbox ══════════ --}}
    @if($formOpen)
        @php $lbAccent = $site->theme['accent'] ?? '#6366f1'; @endphp
        <div class="fixed inset-0 z-50 grid place-items-center p-6" wire:key="invoice-form-lb"
             style="background:rgba(10,10,12,.85); backdrop-filter:blur(6px)" wire:click.self="closeForm">

            {{-- slim cream frame (same chrome as the booking/invoice cards) --}}
            <div class="relative w-full max-w-lg"
                 style="border:.75rem solid rgba(255,249,238,.22); border-radius:28px; background:rgba(255,249,238,.22); box-shadow:0 24px 70px rgba(0,0,0,.5)">

                {{-- BIG accent close --}}
                <button type="button" wire:click="closeForm" aria-label="Close"
                        class="absolute -top-9 -right-9 z-10 w-14 h-14 rounded-full grid place-items-center text-white text-2xl font-bold transition-transform hover:scale-110"
                        style="background:{{ $lbAccent }}; box-shadow:0 8px 24px rgba(0,0,0,.35)">✕</button>

                <div class="max-h-[84vh] overflow-y-auto bg-white dark:bg-[#1d1e2a] rounded-xl p-5">
<div id="inv-form" class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 scroll-mt-6">
                <h2 class="text-sm font-bold mb-3">{{ $editingId ? 'Edit invoice' : 'New invoice' }}</h2>
                <form wire:submit="saveInvoice" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-field.text label="Customer name" model="customerName" />
                            @error('customerName')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-field.text label="Customer email" model="customerEmail" type="email" />
                            @error('customerEmail')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <p class="bkf-label">Line items</p>
                        <div class="space-y-1.5">
                            @foreach($items as $i => $item)
                                <div class="flex gap-1.5" wire:key="item-{{ $i }}">
                                    <div class="flex-1 min-w-0"><x-field.text model="items.{{ $i }}.description" placeholder="Description" /></div>
                                    <div class="w-16"><x-field.text model="items.{{ $i }}.qty" type="number" min="1" placeholder="Qty" /></div>
                                    <div class="w-24"><x-field.text model="items.{{ $i }}.price" type="number" step="0.01" min="0" placeholder="Price" /></div>
                                    <button type="button" wire:click="removeItem({{ $i }})" class="shrink-0 w-8 rounded-lg text-gray-300 hover:text-rose-500" title="Remove line">✕</button>
                                </div>
                            @endforeach
                        </div>
                        @error('items.*.description')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        @error('items.*.price')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                        <button type="button" wire:click="addItem" class="mt-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">＋ Add line</button>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <x-field.text label="Due date" model="dueDate" type="date" />
                        <x-field.text label="Tax %" model="taxPercent" type="number" step="0.01" min="0" max="100" />
                        <div>
                            <label class="bkf-label">Repeat</label>
                            <select wire:model="recurInterval" class="bkf-input">
                                <option value="">One-off</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            <p class="bkf-hint">Recurring invoices are generated and emailed automatically.</p>
                        </div>
                        <div>
                            <label class="bkf-label">Currency</label>
                            <select wire:model="invCurrency" class="bkf-input">
                                @foreach(\App\Support\Money::options() as $code => $label)
                                    <option value="{{ $code }}">{{ strtoupper($code) }} — {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-field.textarea model="invNotes" rows="2" placeholder="Notes shown on the invoice (optional)" />

                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">{{ $editingId ? 'Update invoice' : 'Create draft' }}</button>
                        @if($editingId)
                            <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</button>
                        @endif
                    </div>
                </form>
            </div>
        
            </div>
        </div>
    @endif
    {{-- ══════════ INVOICE DETAIL — dark card lightbox ══════════ --}}
    @if($this->viewedInvoice)
        @php
            $vi = $this->viewedInvoice;
            $vAccent = $site->theme['accent'] ?? '#6366f1';
        @endphp
        <div class="fixed inset-0 z-50 grid place-items-center p-6" wire:key="invoice-detail"
             style="background:rgba(10,10,12,.85); backdrop-filter:blur(6px)" wire:click.self="closeInvoice">

            {{-- slim cream frame --}}
            <div class="relative w-full max-w-md"
                 style="border:.75rem solid rgba(255,249,238,.22); border-radius:28px; background:rgba(255,249,238,.22); box-shadow:0 24px 70px rgba(0,0,0,.5)">

                <button type="button" wire:click="closeInvoice" aria-label="Close"
                        class="absolute -top-9 -right-9 z-10 w-14 h-14 rounded-full grid place-items-center text-white text-2xl font-bold transition-transform hover:scale-110"
                        style="background:{{ $vAccent }}; box-shadow:0 8px 24px rgba(0,0,0,.35)">✕</button>

                {{-- the card component --}}
                <x-invoice-card :invoice="$vi" :accent="$vAccent" :view-url="url($site->name.'/invoices/'.$vi->id)">
                    <x-slot:actions>
                        @if(in_array($vi->status, ['sent', 'overdue', 'draft'], true))
                            <button type="button" wire:click="markPaid({{ $vi->id }})" data-confirm="Mark {{ $vi->number }} as paid?"
                                    class="text-white/90 hover:text-white underline underline-offset-2">Mark paid</button>
                        @endif
                        @if(! in_array($vi->status, ['cancelled', 'paid'], true))
                            <button type="button" wire:click="cancelInvoice({{ $vi->id }})" data-confirm="Cancel invoice {{ $vi->number }}?"
                                    class="text-white/90 hover:text-white underline underline-offset-2">Cancel</button>
                        @endif
                        <a href="{{ $vi->payUrl() }}" target="_blank" rel="noopener"
                           class="text-white/90 hover:text-white underline underline-offset-2">Pay page ↗</a>
                        <a href="{{ url($site->name.'/invoices/'.$vi->id) }}"
                           class="text-white font-bold hover:text-white underline underline-offset-2">View invoice →</a>
                    </x-slot:actions>
                </x-invoice-card>

                {{-- timeline + action links now live INSIDE the card's accent lifecycle box --}}

                {{-- cream CTA bar (Choose-Design-Only treatment) --}}
                @php
                    $cta = null;
                    if (in_array($vi->status, ['draft', 'sent', 'overdue'], true)) {
                        $cta = $vi->sent_at
                            ? ['sendInvoice('.$vi->id.')', 'Resend invoice', 'Email this invoice with its pay link to '.$vi->customer_email.' again?']
                            : ['sendInvoice('.$vi->id.')', 'Send invoice', 'Email this invoice with its pay link to '.$vi->customer_email.'?'];
                    }
                @endphp
                @if($cta)
                    <button type="button" wire:click="{{ $cta[0] }}" data-confirm="{{ $cta[2] }}"
                            class="mt-3 w-full py-3.5 rounded-2xl text-[15px] font-bold text-[#211d15] transition-transform hover:scale-[1.01]"
                            style="background:linear-gradient(180deg, #f7efdb 0%, #e3d3ae 100%); box-shadow:0 10px 24px rgba(0,0,0,.35)">{{ $cta[1] }}</button>
                @endif

                {{-- meta --}}
                <div class="mt-3 py-3 text-center text-[11px] font-semibold">
                    <span class="text-gray-400">{{ $vi->customer_email }}@if($vi->sent_at) · sent {{ $vi->sent_at->format('M j') }}@endif</span>
                </div>
            </div>
        </div>
    @endif
</div>
