@php
    $o = $this->overview;
    $fmt = $o['fmt'];
    $counts = $o['counts'];
    $total = $o['total'];
    $invoices = $this->invoices;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $iconBtn = 'p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors';
    $filters = [
        'all' => ['All', $counts['all']],
        'draft' => ['Draft', $counts['draft']],
        'sent' => ['Sent', $counts['sent']],
        'overdue' => ['Overdue', $counts['overdue']],
        'paid' => ['Paid', $counts['paid']],
        'recurring' => ['Recurring', $counts['recurring']],
        'void' => ['Void', $counts['void']],
    ];
    // Tile-only filters show up as an extra pill while active.
    if ($statusFilter === 'outstanding') $filters['outstanding'] = ['Outstanding', $counts['outstanding']];
    if ($statusFilter === 'month') $filters['month'] = ['Created this month', $counts['month']];
    $statusStyle = [
        'paid' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        'sent' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'overdue' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
        'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
        'draft' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    ];
    $statusLabel = ['cancelled' => 'void'];
    $today = now()->startOfDay();
    // Due-date line + whether it's late: [text, isLate]
    $dueInfo = function ($inv) use ($today) {
        if ($inv->status === 'paid') return [$inv->paid_at ? 'Paid '.$inv->paid_at->format('M j, Y') : 'Paid', false];
        if (! $inv->due_date) return ['No due date', false];
        if ($inv->status === 'cancelled') return ['Was due '.$inv->due_date->format('M j, Y'), false];
        $d = (int) $today->diffInDays($inv->due_date->copy()->startOfDay(), false);
        if ($d < 0) return [abs($d).' '.Str::plural('day', abs($d)).' overdue', true];
        if ($d === 0) return ['Due today', false];
        return ['Due '.$inv->due_date->format('M j').' · in '.$d.' '.Str::plural('day', $d), false];
    };
    $pdfUrl = fn ($inv) => route('site.invoice.pdf', [$site->name, $inv->id]);
    $agingColor = ['current' => '#10b981', '1-30' => '#f59e0b', '31-60' => '#f97316', '60+' => '#e11d48'];
    $agingLabel = ['current' => 'Not yet due', '1-30' => '1–30 days late', '31-60' => '31–60 days late', '60+' => '60+ days late'];
    $plan = $o['plan'];
    $atCap = $plan['cap'] !== null && $plan['used'] >= $plan['cap'];
    $iconSend = 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8';
    $iconCheck = 'M5 13l4 4L19 7';
    $iconPdf = 'M12 10v6m0 0l-3-3m3 3l3-3M6 20h12a2 2 0 002-2V8.4a2 2 0 00-.6-1.4l-3.4-3.4A2 2 0 0014.6 3H6a2 2 0 00-2 2v13a2 2 0 002 2z';
    $iconEye = 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z';
    $iconEdit = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
    $iconLink = 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14';
    $iconX = 'M6 18L18 6M6 6l12 12';
    $iconTrash = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
@endphp
<div wire:key="invoices-{{ $site->id }}">
<x-tri-layout title="Invoices" subtitle="Bill customers, get paid online and chase what's late." :site-name="$site->name"
    :labels="['📊 Stats', '🧾 Invoices', '📋 Summary']">

    {{-- ── LEFT rail: the money at a glance (each tile filters the list) ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$fmt($o['outstanding'])" label="Outstanding"
                :sub="$counts['outstanding'] ? $counts['outstanding'].' open'.($counts['overdue'] ? ' · '.$counts['overdue'].' overdue' : '') : 'nothing owed'"
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                wire:click="setFilter('outstanding')" role="button" class="cursor-pointer {{ $statusFilter === 'outstanding' ? 'ring-2 ring-[color-mix(in_srgb,var(--primary)_60%,transparent)]' : '' }}" />
        <x-tile :accent="$counts['overdue'] ? 'rose' : 'lime'" :value="$fmt($o['overdueCents'])" label="Overdue"
                :sub="$counts['overdue'] ? $counts['overdue'].' '.Str::plural('invoice', $counts['overdue']).' to chase' : 'nothing overdue'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                wire:click="setFilter('overdue')" role="button" class="cursor-pointer {{ $statusFilter === 'overdue' ? 'ring-2 ring-rose-300 dark:ring-rose-500/40' : '' }}" />
        <x-tile accent="lime" :value="$fmt($o['paidMonth'])" label="Paid this month"
                :sub="$o['paidMonthN'] ? $o['paidMonthN'].' '.Str::plural('invoice', $o['paidMonthN']).' paid' : 'none paid yet'"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                wire:click="setFilter('paid')" role="button" class="cursor-pointer {{ $statusFilter === 'paid' ? 'ring-2 ring-emerald-300 dark:ring-emerald-500/40' : '' }}" />
        <x-tile accent="cocoa" :value="number_format($counts['draft'])" label="Drafts"
                :sub="$o['oldestDraftDays'] !== null ? 'oldest '.$o['oldestDraftDays'].'d · not sent' : 'all sent'"
                icon="{{ $iconEdit }}"
                wire:click="setFilter('draft')" role="button" class="cursor-pointer {{ $statusFilter === 'draft' ? 'ring-2 ring-amber-300 dark:ring-amber-500/40' : '' }}" />
        <x-tile accent="sky" :value="$o['avgDays'] !== null ? $o['avgDays'].' '.Str::plural('day', (int) ceil($o['avgDays'])) : '—'" label="Average days to pay"
                :sub="$o['paidN'] ? 'across '.$o['paidN'].' paid' : 'no payments yet'"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                wire:click="setFilter('paid')" role="button" class="cursor-pointer" />
        <x-tile :accent="$atCap ? 'rose' : 'lavender'"
                :value="$plan['cap'] === null ? number_format($plan['used']) : $plan['used'].' / '.$plan['cap']" label="Invoices this month"
                :sub="$plan['cap'] === null ? 'unlimited on your plan' : ($atCap ? 'limit reached — upgrade' : ($plan['cap'] - $plan['used']).' left on your plan')"
                icon="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                wire:click="setFilter('month')" role="button" class="cursor-pointer {{ $statusFilter === 'month' ? 'ring-2 ring-violet-300 dark:ring-violet-500/40' : '' }}" />
    </div>
    </x-slot:rail>

    {{-- ── CENTER: toolbar + the invoices ── --}}
    <div class="space-y-5">

    @unless($site->stripeReady())
        <p class="text-xs px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-400">
            Stripe isn't connected — invoices can be sent but not paid online. Connect it on <a href="{{ route('site.payments', $site->name) }}" class="font-semibold underline">Payments</a>.
        </p>
    @endunless

    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search by number, customer or email…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="newest">Newest first</option>
                <option value="due">Due soonest</option>
                <option value="amount">Largest amount</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <button type="button" wire:click="openForm"
                    class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New invoice
            </button>
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($filters as $key => [$label, $n])
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $statusFilter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $n }}</span>
                </button>
            @endforeach
        </div>
        @if ($clientFilter !== 'all')
            <div class="flex items-center gap-2 text-[12px] text-gray-500 dark:text-gray-400">
                Customer:
                <button type="button" wire:click="filterClient('{{ $clientFilter }}')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20">
                    {{ $clientFilter }} <span aria-hidden="true">✕</span>
                </button>
            </div>
        @endif
    </div>

    @if ($invoices->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            </span>
            @if ($total === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No invoices yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Bill a customer in under a minute — they get an email with a secure pay link, and reminders go out automatically near the due date.</p>
                <button type="button" wire:click="openForm" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create your first invoice</button>
            @elseif ($statusFilter === 'overdue' && $search === '' && $clientFilter === 'all')
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing overdue</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Every sent invoice is within its due date — nice.</p>
                <button type="button" wire:click="resetFilters" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all invoices</button>
            @elseif ($statusFilter === 'draft' && $search === '' && $clientFilter === 'all')
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No drafts</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Everything you've written has been sent.</p>
                <button type="button" wire:click="openForm" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">New invoice</button>
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or filter.</p>
                <button type="button" wire:click="resetFilters" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all invoices</button>
            @endif
        </div>
    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($invoices as $inv)
                @php [$dueText, $late] = $dueInfo($inv); $open = ! in_array($inv->status, ['paid', 'cancelled'], true); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden" wire:key="inv-{{ $inv->id }}">
                    <button type="button" wire:click="viewInvoice('{{ $inv->id }}')" class="p-5 flex-1 block w-full text-left" title="View details">
                        <span class="flex items-start gap-3">
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="text-[15px] font-bold text-gray-900 dark:text-white group-hover:underline">{{ $inv->number }}</span>
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $statusStyle[$inv->status] ?? $statusStyle['draft'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $statusLabel[$inv->status] ?? $inv->status }}
                                    </span>
                                </span>
                                <span class="block mt-1 text-[13px] font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $inv->customer_name }}</span>
                                <span class="block text-[12px] text-gray-400 truncate">{{ $inv->customer_email }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xl font-extrabold tabular-nums leading-none text-gray-900 dark:text-white">{{ $inv->formattedTotal() }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ $inv->created_at?->format('M j') }}</span>
                            </span>
                        </span>
                        <span class="mt-3 flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $late ? 'bg-rose-500 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300' }}">{{ $dueText }}</span>
                            @if ($inv->isRecurring())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300" title="Repeats automatically">↻ {{ ucfirst($inv->recur_interval) }}</span>
                            @endif
                            @if ($inv->parent_invoice_id)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">auto from {{ $inv->parent?->number }}</span>
                            @endif
                            @if ($inv->opened_at || $inv->viewed_at)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300"
                                      title="Opened {{ $inv->opened_at?->format('M j g:i A') ?? '—' }} · Viewed {{ $inv->viewed_at?->format('M j g:i A') ?? '—' }}">{{ $inv->viewed_at ? '👁 Viewed' : '✉ Opened' }}</span>
                            @endif
                            @if ($inv->reminders_sent > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300">{{ $inv->reminders_sent }} {{ Str::plural('reminder', $inv->reminders_sent) }}</span>
                            @endif
                        </span>
                    </button>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        @if ($open)
                            <button type="button" wire:click="sendInvoice('{{ $inv->id }}')"
                                    data-confirm="Email invoice {{ $inv->number }} with its pay link to {{ $inv->customer_email }}?"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">
                                <svg class="w-3.5 h-3.5 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconSend }}"/></svg>
                                {{ $inv->sent_at ? 'Resend' : 'Send' }}
                            </button>
                            <button type="button" wire:click="markPaid('{{ $inv->id }}')"
                                    data-confirm="Mark {{ $inv->number }} as paid ({{ $inv->formattedTotal() }})?"
                                    class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Mark paid</button>
                        @else
                            <button type="button" wire:click="viewInvoice('{{ $inv->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">View</button>
                        @endif
                        <span class="ml-auto flex items-center gap-0.5">
                            @if ($open)
                                <button type="button" wire:click="viewInvoice('{{ $inv->id }}')" title="View" class="{{ $iconBtn }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconEye }}"/></svg>
                                </button>
                            @endif
                            <a href="{{ $pdfUrl($inv) }}" title="Download PDF" class="{{ $iconBtn }}"
                               data-download-progress data-filename="{{ $inv->number }}.pdf" data-label="Preparing {{ $inv->number }} PDF…">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPdf }}"/></svg>
                            </a>
                            <a href="{{ $inv->payUrl() }}" target="_blank" rel="noopener" title="Open the public pay page" class="{{ $iconBtn }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconLink }}"/></svg>
                            </a>
                            @if ($inv->status === 'draft')
                                <button type="button" wire:click="editInvoice('{{ $inv->id }}')" title="Edit" class="{{ $iconBtn }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconEdit }}"/></svg>
                                </button>
                            @endif
                            @if ($inv->status !== 'paid')
                                <button type="button" wire:click="{{ $inv->status === 'cancelled' ? 'deleteInvoice' : 'cancelInvoice' }}('{{ $inv->id }}')"
                                        data-confirm="{{ $inv->status === 'cancelled' ? 'Delete invoice '.$inv->number.' permanently?' : 'Void invoice '.$inv->number.'? The pay link stops working.' }}"
                                        title="{{ $inv->status === 'cancelled' ? 'Delete' : 'Void' }}"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $inv->status === 'cancelled' ? $iconTrash : $iconX }}"/></svg>
                                </button>
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Invoice</th>
                            @unless ($compact)<th class="px-4 py-3">Due</th>@endunless
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="w-36 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($invoices as $inv)
                            @php [$dueText, $late] = $dueInfo($inv); $open = ! in_array($inv->status, ['paid', 'cancelled'], true); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group" wire:key="row-{{ $inv->id }}">
                                <td class="{{ $pad }}">
                                    <button type="button" wire:click="viewInvoice('{{ $inv->id }}')" class="block text-left min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="font-semibold text-gray-900 dark:text-white hover:underline whitespace-nowrap">{{ $inv->number }}</span>
                                            @if ($inv->isRecurring())<span class="text-[11px] font-semibold text-violet-600 dark:text-violet-300" title="Repeats {{ $inv->recur_interval }}">↻</span>@endif
                                            <span class="text-gray-400 truncate max-w-[12rem]">· {{ $inv->customer_name }}</span>
                                        </span>
                                        @unless ($compact)
                                            <span class="block text-[11px] text-gray-400 truncate max-w-[18rem]">{{ $inv->customer_email }}@if ($inv->parent_invoice_id) · auto from {{ $inv->parent?->number }}@endif @if ($inv->reminders_sent > 0) · {{ $inv->reminders_sent }} {{ Str::plural('reminder', $inv->reminders_sent) }}@endif</span>
                                        @endunless
                                    </button>
                                </td>
                                @unless ($compact)
                                    <td class="{{ $pad }} text-[12px] whitespace-nowrap {{ $late ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-gray-500 dark:text-gray-400' }}">{{ $dueText }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full whitespace-nowrap {{ $statusStyle[$inv->status] ?? $statusStyle['draft'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $statusLabel[$inv->status] ?? $inv->status }}
                                    </span>
                                </td>
                                <td class="{{ $pad }} text-right font-bold tabular-nums text-gray-900 dark:text-white whitespace-nowrap">{{ $inv->formattedTotal() }}</td>
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-0.5 justify-end">
                                        @if ($open)
                                            <button type="button" wire:click="sendInvoice('{{ $inv->id }}')" title="{{ $inv->sent_at ? 'Resend' : 'Send' }} with pay link"
                                                    data-confirm="Email invoice {{ $inv->number }} with its pay link to {{ $inv->customer_email }}?" class="{{ $iconBtn }}">
                                                <svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconSend }}"/></svg>
                                            </button>
                                            <button type="button" wire:click="markPaid('{{ $inv->id }}')" title="Mark paid"
                                                    data-confirm="Mark {{ $inv->number }} as paid ({{ $inv->formattedTotal() }})?"
                                                    class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconCheck }}"/></svg>
                                            </button>
                                        @endif
                                        <a href="{{ $pdfUrl($inv) }}" title="Download PDF" class="{{ $iconBtn }}"
                                           data-download-progress data-filename="{{ $inv->number }}.pdf" data-label="Preparing {{ $inv->number }} PDF…">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPdf }}"/></svg>
                                        </a>
                                        @if ($inv->status === 'draft')
                                            <button type="button" wire:click="editInvoice('{{ $inv->id }}')" title="Edit" class="{{ $iconBtn }}">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconEdit }}"/></svg>
                                            </button>
                                        @endif
                                        @if ($inv->status !== 'paid')
                                            <button type="button" wire:click="{{ $inv->status === 'cancelled' ? 'deleteInvoice' : 'cancelInvoice' }}('{{ $inv->id }}')"
                                                    data-confirm="{{ $inv->status === 'cancelled' ? 'Delete invoice '.$inv->number.' permanently?' : 'Void invoice '.$inv->number.'? The pay link stops working.' }}"
                                                    title="{{ $inv->status === 'cancelled' ? 'Delete' : 'Void' }}"
                                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $inv->status === 'cancelled' ? $iconTrash : $iconX }}"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($invoices->hasPages())
        <div>{{ $invoices->links() }}</div>
    @endif

    </div>

    {{-- ══ RIGHT rail: receivables · needs attention · customers · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Receivables</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $fmt($o['outstanding']) }}</b> outstanding across
                <b class="text-gray-900 dark:text-white">{{ $counts['outstanding'] }}</b> {{ Str::plural('invoice', $counts['outstanding']) }}
            </p>
            @if ($o['outstanding'] > 0)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]" role="img" aria-label="Aged receivables">
                    @foreach ($o['aging'] as $k => $c)
                        @if ($c)<span style="width:{{ round($c / $o['outstanding'] * 100, 2) }}%;background:{{ $agingColor[$k] }}" title="{{ $agingLabel[$k] }} · {{ $fmt($c) }}"></span>@endif
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach ($o['aging'] as $k => $c)
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $agingColor[$k] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $agingLabel[$k] }}</span>
                            <span class="ml-auto font-bold tabular-nums {{ $c ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $fmt($c) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-[12px] text-gray-400">Nothing waiting to be paid.</p>
            @endif

            @php $cv = $o['collected'] + $o['outstanding']; $pctCollected = $cv ? round($o['collected'] / $cv * 100) : 0; @endphp
            <p class="mt-4 mb-1.5 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">Collected vs outstanding</p>
            <div class="flex items-baseline justify-between text-[12.5px]">
                <span class="text-gray-600 dark:text-gray-300"><b class="text-emerald-600 dark:text-emerald-400">{{ $fmt($o['collected']) }}</b> collected</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ $pctCollected }}%</span>
            </div>
            <div class="mt-1.5 flex h-2 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                @if ($cv)
                    <span style="width:{{ $pctCollected }}%;background:#10b981"></span>
                    <span style="width:{{ 100 - $pctCollected }}%;background:#f59e0b"></span>
                @endif
            </div>
            <p class="mt-1.5 text-[11px] text-gray-400">{{ $fmt($o['outstanding']) }} still to collect · all time</p>
        </div>

        @if ($o['chase']->isNotEmpty() || $o['unsent']->isNotEmpty() || $o['failed']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @foreach ($o['chase'] as $i)
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <button type="button" wire:click="viewInvoice('{{ $i->id }}')" class="block w-full text-left">
                            <span class="block text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $i->number }} · {{ $fmt((int) $i->total_cents) }}</span>
                            <span class="block text-[12px] text-rose-700/80 dark:text-rose-200/70 truncate">{{ $i->customer_name }} — {{ $i->days_late }} {{ Str::plural('day', $i->days_late) }} overdue{{ $i->reminders_sent ? ' · '.$i->reminders_sent.' auto-reminded' : '' }}</span>
                        </button>
                        <button type="button" wire:click="sendInvoice('{{ $i->id }}')" data-confirm="Email {{ $i->number }} again with its pay link to {{ $i->customer_email }}?"
                                class="mt-2 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white dark:bg-[#1d1e2a] text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30 hover:bg-rose-100 dark:hover:bg-rose-500/20">Chase now</button>
                    </div>
                @endforeach
                @if ($o['chase']->count() < $counts['overdue'])
                    <button type="button" wire:click="setFilter('overdue')" class="text-[12px] font-semibold text-rose-600 dark:text-rose-400 hover:underline">All {{ $counts['overdue'] }} overdue →</button>
                @endif
                @foreach ($o['unsent'] as $i)
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 flex items-center gap-2">
                        <button type="button" wire:click="viewInvoice('{{ $i->id }}')" class="min-w-0 flex-1 text-left">
                            <span class="block text-[13px] font-bold text-amber-800 dark:text-amber-200">Draft {{ $i->number }} not sent</span>
                            <span class="block text-[12px] text-amber-700/80 dark:text-amber-200/70 truncate">{{ $i->customer_name }} · {{ $fmt((int) $i->total_cents) }} · {{ $i->created_at->diffForHumans(null, true) }} old</span>
                        </button>
                        <button type="button" wire:click="sendInvoice('{{ $i->id }}')" data-confirm="Email invoice {{ $i->number }} with its pay link to {{ $i->customer_email }}?"
                                class="shrink-0 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-white dark:bg-[#1d1e2a] text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-500/30 hover:bg-amber-100 dark:hover:bg-amber-500/20">Send</button>
                    </div>
                @endforeach
                @foreach ($o['failed'] as $i)
                    <button type="button" wire:click="viewInvoice('{{ $i->id }}')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $i->number }} — payment not completed</span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $i->customer_name }} started checkout for {{ $fmt((int) $i->total_cents) }} but it didn't go through.</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        @if ($o['topCustomers']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Top customers</p>
            @php $maxC = max(1, $o['topCustomers']->max('cents')); @endphp
            <div class="space-y-2.5">
                @foreach ($o['topCustomers'] as $c)
                    <button type="button" wire:click="filterClient('{{ $c['email'] }}')" class="block w-full text-left group" title="Show {{ $c['name'] }}'s invoices">
                        <span class="flex items-center justify-between gap-2 text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline {{ $clientFilter === $c['email'] ? 'font-bold' : '' }}">{{ $c['name'] }}</span>
                            <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ $fmt($c['cents']) }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($c['cents'] / $maxC * 100) }}%;background:var(--primary)"></span>
                        </span>
                        <span class="block mt-0.5 text-[11px] text-gray-400">{{ $c['count'] }} {{ Str::plural('invoice', $c['count']) }}{{ $c['open'] ? ' · '.$fmt($c['open']).' open' : '' }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <div class="flex items-baseline justify-between mb-3">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Reminders</p>
                <span class="text-[11px] text-gray-400">auto · hourly</span>
            </div>
            <div class="space-y-2">
                @forelse ($this->reminderFeed as $r)
                    <button type="button" wire:click="viewInvoice('{{ $r->id }}')" class="w-full text-left flex items-start gap-2.5 group">
                        <span class="shrink-0 w-7 h-7 rounded-full grid place-items-center text-[11px] font-extrabold" style="background:var(--primary);color:var(--on-primary)">{{ strtoupper(mb_substr($r->customer_name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12.5px] font-semibold text-gray-800 dark:text-gray-100 truncate group-hover:underline">{{ $r->customer_name }}@if ($r->status === 'overdue')<span class="text-rose-500"> · overdue</span>@endif</span>
                            <span class="block text-[11px] text-gray-400 truncate">{{ $r->number }} · {{ $r->formattedTotal() }} ·
                                @if ($r->reminders_sent > 0){{ $r->reminders_sent }} sent{{ $r->reminded_at ? ' · '.$r->reminded_at->diffForHumans(short: true) : '' }}@else due {{ $r->due_date?->format('M j') }}@endif</span>
                        </span>
                    </button>
                @empty
                    <p class="text-[12px] text-gray-400">Nothing needs a nudge — reminders send automatically near and past due dates.</p>
                @endforelse
            </div>
        </div>

        @php $months = $this->monthly; $maxM = max(1, collect($months)->max('cents')); $bal = $this->balance; $shares = $this->sourceShares; $srcColors = ['var(--primary)', '#f59e0b', '#10b981', '#ec4899']; @endphp
        <div class="{{ $panel }} p-5">
            <div class="flex items-baseline justify-between">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Revenue</p>
                <span class="text-[11px] text-gray-400">6 months · all sources</span>
            </div>
            <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                <b class="text-gray-900 dark:text-white">{{ \App\Support\Money::format($bal['cents'], $site->currency) }}</b> this month
                @if ($bal['delta'] !== null)<span class="{{ $bal['delta'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }} font-semibold">{{ $bal['delta'] >= 0 ? '▲' : '▼' }} {{ abs($bal['delta']) }}%</span>@endif
            </p>
            <div class="mt-3 flex items-end gap-1.5 h-20" role="img" aria-label="Revenue collected per month, last six months">
                @foreach ($months as $m)
                    @php $isCur = $m['key'] === now()->format('Y-m'); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $m['label'] }} · {{ \App\Support\Money::format($m['cents'], $site->currency) }}">
                        <span class="w-full rounded-md {{ $isCur ? '' : 'bg-gray-200 dark:bg-white/[0.12]' }}"
                              style="height:{{ $m['cents'] ? max(6, round($m['cents'] / $maxM * 100)) : 4 }}%;{{ $isCur ? 'background:var(--primary)' : '' }}"></span>
                        <span class="mt-1 text-[10px] {{ $isCur ? 'font-bold' : 'text-gray-400' }}" @if ($isCur) style="color:var(--primary)" @endif>{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
            @if (count($shares))
                <p class="mt-4 mb-1.5 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">By source · all time</p>
                <div class="flex h-2 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($shares as $i => $c)
                        <span style="width:{{ $c['share'] }}%;background:{{ $srcColors[$i % 4] }}" title="{{ $c['name'] }} · {{ $c['money'] }}"></span>
                    @endforeach
                </div>
                <div class="mt-2 space-y-1">
                    @foreach ($shares as $i => $c)
                        <div class="flex items-center gap-2 text-[12px]">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $srcColors[$i % 4] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $c['name'] }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white tabular-nums">{{ $c['share'] }}%</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_filter([
                    ['Contacts', 'Your customers', route('site.contacts', $site->name)],
                    ['Payments', 'Everything you got paid', route('site.payments', $site->name)],
                    $site->hasFeature('estimator') ? ['Estimates', 'Quotes before invoicing', route('site.estimates', $site->name)] : null,
                    $site->hasFeature('store') ? ['Orders', 'Store sales', route('site.orders', $site->name)] : null,
                    $site->hasFeature('bookings') ? ['Bookings', 'Paid appointments', route('site.bookings', $site->name)] : null,
                    ['Add-ons', 'Due days, tax & reminders', route('site.marketplace', $site->name)],
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

                <div class="max-h-[84vh] overflow-y-auto bg-white dark:bg-[#1d1e2a] rounded-xl p-5 space-y-4">
                    @unless($editingId)
                        {{-- Quick draft from a sentence --}}
                        <div class="rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.1] p-3" x-data="{ open: false }">
                            <button type="button" x-on:click="open = !open" class="w-full flex items-center justify-between text-left">
                                <span class="text-[13px] font-bold text-gray-900 dark:text-white">✨ Draft it from a sentence</span>
                                <span class="text-[11px] text-gray-400" x-text="open ? 'Hide' : 'Show'">Show</span>
                            </button>
                            <div x-show="open" x-cloak class="mt-2">
                                <p class="text-[11px] text-gray-400 mb-2">Describe the invoice — client email + amounts — and a draft appears in the list.</p>
                                <textarea wire:model="genPrompt" rows="3" maxlength="300"
                                          placeholder="Logo design 250 and hosting 40, for Jane Doe jane@studio.com, due in 14 days"
                                          class="bkf-input resize-none text-xs w-full"></textarea>
                                <button type="button" wire:click="generateFromPrompt"
                                        class="mt-2 w-full py-2 rounded-xl text-xs font-bold transition-opacity hover:opacity-90"
                                        style="background:var(--primary);color:var(--on-primary)">Generate draft</button>
                            </div>
                        </div>
                    @endunless
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
                                <button type="button" wire:click="addItem" class="mt-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-[#1d1e2a] text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">＋ Add line</button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <x-field.text label="Due date" model="dueDate" type="date" />
                            </div>

                            <div class="olx-adv-lead">More options</div>
                            <x-panel-group label="Tax, currency & repeat" hint="tax %, recurring, currency">
                                <div class="grid grid-cols-2 gap-3">
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
                                        <p class="bkf-hint">Recurring invoices are generated and emailed automatically.@unless($plan['recurring']) Comes with Pro and above.@endunless</p>
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
                            </x-panel-group>
                            <x-panel-group label="Notes" hint="shown on the invoice">
                                <x-field.textarea model="invNotes" rows="2" placeholder="Notes shown on the invoice (optional)" />
                            </x-panel-group>

                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">{{ $editingId ? 'Update invoice' : 'Create draft' }}</button>
                                <button type="button" wire:click="closeForm" class="{{ $btnSolid }} px-4 py-2 text-sm">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════ INVOICE DETAIL — right-side drawer ══════════ --}}
    @if($this->viewedInvoice)
        @php
            $vi = $this->viewedInvoice;
            $vAccent = $site->theme['accent'] ?? '#6366f1';
        @endphp
        <div class="fixed inset-0 z-50 flex justify-end" wire:key="invoice-detail"
             x-data @keydown.escape.window="$wire.closeInvoice()">
            <div class="lightbox-backdrop absolute inset-0 bg-gray-900/40" wire:click="closeInvoice"></div>

            <div class="lightbox-drawer relative h-full w-full max-w-md bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.06] shadow-2xl flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-white/[0.06] shrink-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">Invoice {{ $vi->number }}</p>
                    <button type="button" wire:click="closeInvoice" title="Close (Esc)" aria-label="Close"
                            class="w-8 h-8 rounded-full grid place-items-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-5 overflow-y-auto grow">
                <x-invoice-card :invoice="$vi" :accent="$vAccent" :view-url="url($site->name.'/invoices/'.$vi->id)">
                    <x-slot:actions>
                        @if(in_array($vi->status, ['sent', 'overdue', 'draft'], true))
                            <button type="button" wire:click="markPaid('{{ $vi->id }}')" data-confirm="Mark {{ $vi->number }} as paid?"
                                    class="text-white/90 hover:text-white underline underline-offset-2">Mark paid</button>
                        @endif
                        @if($vi->status === 'draft')
                            <button type="button" wire:click="editInvoice('{{ $vi->id }}')"
                                    class="text-white/90 hover:text-white underline underline-offset-2">Edit</button>
                        @endif
                        @if(! in_array($vi->status, ['cancelled', 'paid'], true))
                            <button type="button" wire:click="cancelInvoice('{{ $vi->id }}')" data-confirm="Void invoice {{ $vi->number }}? The pay link stops working."
                                    class="text-white/90 hover:text-white underline underline-offset-2">Void</button>
                        @endif
                        <a href="{{ $vi->payUrl() }}" target="_blank" rel="noopener"
                           class="text-white/90 hover:text-white underline underline-offset-2">Pay page ↗</a>
                        <a href="{{ route('site.invoice.pdf', [$site->name, $vi->id]) }}"
                           data-download-progress data-filename="{{ $vi->number }}.pdf" data-label="Preparing {{ $vi->number }} PDF…"
                           class="text-white/90 hover:text-white underline underline-offset-2">PDF</a>
                        <a href="{{ url($site->name.'/invoices/'.$vi->id) }}"
                           class="text-white font-bold hover:text-white underline underline-offset-2">View invoice →</a>
                    </x-slot:actions>
                </x-invoice-card>

                @if (in_array($vi->status, ['draft', 'sent', 'overdue'], true))
                    <button type="button" wire:click="sendInvoice('{{ $vi->id }}')"
                            data-confirm="{{ $vi->sent_at ? 'Email this invoice with its pay link to '.$vi->customer_email.' again?' : 'Email this invoice with its pay link to '.$vi->customer_email.'?' }}"
                            class="mt-3 w-full py-3.5 rounded-2xl text-[15px] font-bold text-[#211d15] transition-transform hover:scale-[1.01]"
                            style="background:linear-gradient(180deg, #f7efdb 0%, #e3d3ae 100%); box-shadow:0 10px 24px rgba(0,0,0,.35)">{{ $vi->sent_at ? 'Resend invoice' : 'Send invoice' }}</button>
                @endif

                <div class="mt-3 py-3 text-center text-[11px] font-semibold">
                    <span class="text-gray-400">{{ $vi->customer_email }}@if($vi->sent_at) · sent {{ $vi->sent_at->format('M j') }}@endif</span>
                </div>
                </div>
            </div>
        </div>
    @endif
</div>
