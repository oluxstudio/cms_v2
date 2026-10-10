{{--
    Booking detail — the body of the booking drawer on the Bookings page.
    Sections: identity (service · reference · status) → when (date tile, time,
    staff) → customer (contact actions, message, form answers) → payment
    (total · paid · balance) → progress (Created → Confirmed → Paid/Cancelled).
    Light + dark, theme accent, readable sizes. Actions live in the drawer's
    pinned footer (the default slot is rendered there by the page).
    Props: booking (App\Models\Booking), accent (hex).
--}}
@props(['booking', 'accent' => '#6366f1'])
@php
    $b = $booking;
    $p = (array) ($b->params ?? []);
    $kind = $b->service?->kind ?? 'slot';
    $status = [
        'confirmed' => ['Confirmed', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'bg-emerald-500'],
        'pending' => ['Pending', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'bg-amber-500'],
        'awaiting_payment' => ['Awaiting payment', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'bg-sky-500'],
        'no_show' => ['No-show', 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300', 'bg-orange-500'],
        'cancelled' => ['Cancelled', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'bg-rose-500'],
    ][$b->status] ?? [ucfirst((string) $b->status), 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300', 'bg-gray-400'];

    $start = $b->starts_at;
    $end = $b->ends_at;
    $minutes = $start && $end ? $start->diffInMinutes($end) : null;
    $duration = $minutes === null ? null : ($minutes >= 60 ? intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '') : $minutes.' min');
    $relative = $start ? ($start->isToday() ? 'Today' : ($start->isTomorrow() ? 'Tomorrow' : ($start->isPast() ? $start->diffForHumans() : 'in '.$start->diffForHumans(null, true)))) : null;

    // Payment section only when the SERVICE takes payment (or money was collected).
    $paymentRelevant = $b->total_cents > 0 && (($b->service?->requires_payment ?? false) || $b->paid_cents > 0);
    $paidPct = $b->total_cents > 0 ? min(100, (int) round($b->paid_cents / $b->total_cents * 100)) : 0;
    $payState = $b->balanceCents() === 0 ? ['Paid in full', 'text-emerald-600 dark:text-emerald-400']
        : ($b->paid_cents > 0 ? ['Deposit paid', 'text-sky-600 dark:text-sky-400'] : ['Unpaid', 'text-amber-600 dark:text-amber-400']);

    $initials = collect(preg_split('/\s+/', trim((string) $b->customer_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $answers = collect((array) ($p['fields'] ?? []))->filter(fn ($v) => filled($v));

    $section = 'rounded-2xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-white/[0.03]';
    $label = 'text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 dark:text-gray-500';
@endphp
<div {{ $attributes->merge(['class' => 'space-y-3']) }}>

    {{-- ── Identity ── --}}
    <div class="flex items-start gap-3">
        <span class="shrink-0 w-12 h-12 rounded-2xl grid place-items-center text-xl" style="background:{{ $accent }}1a">{{ $b->service?->typeIcon() ?? '📅' }}</span>
        <div class="min-w-0 flex-1">
            <p class="text-[17px] font-extrabold tracking-tight text-gray-900 dark:text-white truncate">{{ $b->service?->name ?? 'Service' }}</p>
            <button type="button" x-data="{ c: false }" x-on:click="navigator.clipboard?.writeText(@js($b->reference)); c = true; setTimeout(() => c = false, 1500)"
                    class="mt-0.5 inline-flex items-center gap-1.5 font-mono text-[12px] tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white" title="Copy reference">
                {{ $b->reference }}
                <span x-show="! c" class="text-[10px]">⧉</span><span x-show="c" x-cloak class="text-[10px] text-emerald-500 font-sans font-bold">Copied</span>
            </button>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11.5px] font-bold {{ $status[1] }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $status[2] }}"></span>{{ $status[0] }}
        </span>
    </div>

    {{-- ── When ── --}}
    <div class="{{ $section }} p-4 flex items-center gap-4">
        @if ($kind === 'slot' && $start)
            <div class="shrink-0 w-16 rounded-xl overflow-hidden text-center shadow-sm border border-gray-100 dark:border-white/[0.08]">
                <p class="text-[10px] font-bold uppercase tracking-wider py-0.5 text-white" style="background:{{ $accent }}">{{ $start->format('M') }}</p>
                <p class="text-[24px] font-extrabold leading-tight text-gray-900 dark:text-white bg-white dark:bg-[#1d1e2a]">{{ $start->format('j') }}</p>
                <p class="text-[10px] font-semibold text-gray-500 pb-1 bg-white dark:bg-[#1d1e2a]">{{ $start->format('D') }}</p>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[17px] font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $start->format('g:i A') }}@if($end) – {{ $end->format('g:i A') }}@endif</p>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $start->format('l j F Y') }}@if($duration) · {{ $duration }}@endif</p>
                @if ($b->resource)
                    <p class="mt-1.5 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        {{ ucfirst($b->service?->resourceNoun() ?? 'with') }}: {{ $b->resource->name }}
                    </p>
                @endif
            </div>
            @if ($relative)
                <span class="shrink-0 self-start text-[11px] font-bold px-2 py-0.5 rounded-full {{ $start->isPast() ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-500' : 'text-white' }}"
                      @unless ($start->isPast()) style="background:{{ $accent }}" @endunless>{{ $relative }}</span>
            @endif
        @elseif ($kind === 'stay')
            <div class="min-w-0 flex-1">
                <p class="{{ $label }}">Stay</p>
                <p class="text-[17px] font-extrabold text-gray-900 dark:text-white mt-1">{{ $p['check_in'] ?? '?' }} → {{ $p['check_out'] ?? '?' }}</p>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $p['nights'] ?? '?' }} {{ Str::plural('night', (int) ($p['nights'] ?? 2)) }} · {{ $p['guests'] ?? 1 }} {{ Str::plural('guest', (int) ($p['guests'] ?? 1)) }} · {{ $b->quantity }} {{ Str::plural('unit', (int) $b->quantity) }}@if($b->resource) · {{ $b->resource->name }}@endif</p>
            </div>
        @else
            <div class="min-w-0 flex-1">
                <p class="{{ $label }}">Trip</p>
                <p class="text-[17px] font-extrabold text-gray-900 dark:text-white mt-1">{{ $p['origin'] ?? '?' }} → {{ $p['destination'] ?? '?' }}</p>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $start?->format('D j M Y · g:i A') }} · {{ $b->quantity }} {{ Str::plural('seat', (int) $b->quantity) }}</p>
            </div>
        @endif
    </div>

    {{-- ── Customer ── --}}
    <div class="{{ $section }} p-4">
        <div class="flex items-center gap-3">
            <span class="shrink-0 w-10 h-10 rounded-full grid place-items-center text-[13px] font-extrabold text-white" style="background:{{ $accent }}">{{ $initials }}</span>
            <div class="min-w-0 flex-1">
                <p class="text-[15px] font-bold text-gray-900 dark:text-white truncate">{{ $b->customer_name }}</p>
                <p class="text-[12px] text-gray-500 dark:text-gray-400">Customer</p>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="mailto:{{ $b->customer_email }}" class="group flex items-center gap-2.5 rounded-xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:bg-gray-100 dark:hover:bg-white/[0.07] min-w-0">
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate group-hover:underline">{{ $b->customer_email }}</span>
            </a>
            @if ($b->customer_phone)
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $b->customer_phone) }}" class="group flex items-center gap-2.5 rounded-xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:bg-gray-100 dark:hover:bg-white/[0.07] min-w-0">
                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.49a1 1 0 01-.5 1.21l-2.26 1.13a11.04 11.04 0 005.52 5.52l1.13-2.26a1 1 0 011.21-.5l4.49 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.72 21 3 14.28 3 6V5z"/></svg>
                    <span class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 tabular-nums truncate group-hover:underline">{{ $b->customer_phone }}</span>
                </a>
            @endif
        </div>
        @if ($b->notes)
            <div class="mt-3 rounded-xl px-3.5 py-3 text-[13px] leading-relaxed text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-white/[0.04] border-l-[3px]" style="border-color:{{ $accent }}">
                <p class="{{ $label }} mb-1">Message</p>
                {{ $b->notes }}
            </div>
        @endif
        @if ($answers->isNotEmpty())
            <dl class="mt-3 divide-y divide-gray-100 dark:divide-white/[0.05]">
                @foreach ($answers as $fk => $fv)
                    <div class="flex items-start justify-between gap-4 py-2 text-[13px]">
                        <dt class="text-gray-500 dark:text-gray-400">{{ Str::headline($fk) }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white text-right break-words">{{ is_array($fv) ? implode(', ', $fv) : $fv }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    {{-- ── Payment ── --}}
    @if ($paymentRelevant)
        <div class="{{ $section }} p-4">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="{{ $label }}">Total</p>
                    <p class="mt-1 text-[26px] font-extrabold tracking-tight leading-none tabular-nums text-gray-900 dark:text-white">{{ $b->formattedTotal() }}</p>
                </div>
                <p class="text-[13px] font-bold {{ $payState[1] }}">{{ $payState[0] }}</p>
            </div>
            <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                <div class="h-full rounded-full {{ $paidPct === 100 ? 'bg-emerald-500' : '' }}" style="width:{{ $paidPct }}%;{{ $paidPct === 100 ? '' : 'background:'.$accent }}"></div>
            </div>
            <div class="mt-2 flex justify-between text-[12px] text-gray-500 dark:text-gray-400 tabular-nums">
                <span>Paid {{ $b->formattedPaid() }}</span>
                <span>{{ $b->balanceCents() > 0 ? 'Balance '.$b->formattedBalance() : 'Nothing left to pay' }}</span>
            </div>
        </div>
    @endif

    {{-- ── Progress ── --}}
    <div class="{{ $section }} p-4">
        <p class="{{ $label }} mb-3">Progress</p>
        <ol class="relative space-y-3">
            @foreach ($b->timeline() as $i => $step)
                @php $done = (bool) $step['at']; $bad = $step['label'] === 'Cancelled'; @endphp
                <li class="flex items-start gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-[11px] font-bold
                                 {{ $done ? ($bad ? 'bg-rose-500 text-white' : 'text-white') : 'bg-gray-100 dark:bg-white/[0.07] text-gray-400' }}"
                          @if ($done && ! $bad) style="background:{{ $accent }}" @endif>{{ $done ? ($bad ? '✕' : '✓') : $i + 1 }}</span>
                    <div class="min-w-0 flex-1 flex items-baseline justify-between gap-3">
                        <span class="text-[13.5px] font-semibold {{ $done ? ($bad ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white') : 'text-gray-400' }}">{{ $step['label'] }}</span>
                        <span class="text-[12px] tabular-nums text-gray-400">{{ $step['at']?->format('j M · g:i A') ?? 'Not yet' }}</span>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
