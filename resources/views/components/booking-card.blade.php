{{--
    Booking card — professional dark card on a cream base.
    Structure: identity header → accent summary strip → definition rows →
    total + status. One accent element, stroke-icon system, tabular numerals.
    Props: booking (App\Models\Booking), accent (hex).
--}}
@props(['booking', 'accent' => '#6366f1'])
@php
    $b = $booking;
    $p = (array) ($b->params ?? []);
    $kind = $b->service?->kind ?? 'slot';
    $statusChip = ['confirmed' => 'Confirmed', 'pending' => 'Pending',
                   'awaiting_payment' => 'Awaiting payment', 'cancelled' => 'Cancelled'][$b->status] ?? ucfirst($b->status);

    $when = match ($kind) {
        'stay'  => ($p['check_in'] ?? '?').' → '.($p['check_out'] ?? '?'),
        'trip'  => ($p['origin'] ?? '?').' → '.($p['destination'] ?? '?'),
        default => $b->starts_at?->format('D, M j, Y'),
    };
    $whenSub = match ($kind) {
        'stay'  => ($p['nights'] ?? '?').' night(s) · '.($p['guests'] ?? 1).' guest(s)',
        'trip'  => $b->starts_at?->format('D, M j, Y · g:i A'),
        default => $b->starts_at?->format('g:i A').' – '.$b->ends_at?->format('g:i A'),
    };

    // Payment section only matters when the SERVICE takes payment (or money
    // was actually collected) — services without payment hide it entirely.
    $paymentRelevant = $b->total_cents > 0
        && (($b->service?->requires_payment ?? false) || $b->paid_cents > 0);

    [$int, $dec] = $b->total_cents > 0
        ? explode('.', number_format($b->total_cents / 100, 2))
        : ['Free', null];

    // Stroke-icon set (Feather-style, 15px, consistent weight)
    $svg = fn (string $d) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
    $icons = [
        'user'  => $svg('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
        'mail'  => $svg('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'),
        'phone' => $svg('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>'),
        'note'  => $svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'),
        'clock' => $svg('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
        'tag'   => $svg('<path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42Z"/><circle cx="7" cy="7" r="1"/>'),
        'users' => $svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    ];

    $rows = [];
    if ($b->resource) $rows[] = ['user', ucfirst($b->service?->resourceNoun() ?? 'resource'), $b->resource->name];
    if ($kind === 'stay') $rows[] = ['users', 'Party', ($p['guests'] ?? 1).' guest(s) · '.$b->quantity.' unit(s)'];
    if ($kind === 'trip') $rows[] = ['tag', 'Seats', $b->quantity];
    $rows[] = ['mail', 'Email', $b->customer_email];
    if ($b->customer_phone) $rows[] = ['phone', 'Phone', $b->customer_phone];
    foreach ((array) ($p['fields'] ?? []) as $fk => $fv) $rows[] = ['tag', \Illuminate\Support\Str::headline($fk), $fv];
    if ($b->notes) $rows[] = ['note', 'Message', $b->notes];
    $rows[] = ['clock', 'Booked', $b->created_at->format('M j, Y · g:i A')];
@endphp
<div {{ $attributes->merge(['class' => 'relative rounded-[22px] p-3']) }}
     style="background:#f6efe0; box-shadow:0 24px 48px -12px rgba(0,0,0,.45)">

    {{-- dark card --}}
    <div class="relative rounded-2xl px-6 pt-6 pb-5 text-[#ece5d8]"
         style="background:#1c1a16; border:1px solid rgba(236,229,216,.07); box-shadow:0 16px 32px -8px rgba(0,0,0,.5)">

        {{-- identity header --}}
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3.5 min-w-0">
                <span class="shrink-0 w-11 h-11 rounded-full grid place-items-center text-lg"
                      style="background:rgba(236,229,216,.06); border:1px solid rgba(236,229,216,.16)">{{ $b->service?->typeIcon() ?? '📅' }}</span>
                <div class="min-w-0">
                    <p class="text-[16px] font-semibold tracking-tight text-[#f6f1e7] truncate">{{ $b->service?->name ?? 'Service' }}</p>
                    <p class="text-[11px] mt-0.5 font-mono tracking-wide text-[#9a8f7e]">{{ $b->reference }}</p>
                </div>
            </div>
            <span class="shrink-0 mt-0.5 px-2.5 py-1 rounded-md text-[10px] font-semibold uppercase tracking-[0.08em]
                {{ $b->status === 'cancelled' ? 'text-rose-300' : 'text-[#e8dfc9]' }}"
                  style="background:rgba(236,229,216,.07); border:1px solid rgba(236,229,216,.14)">{{ $statusChip }}</span>
        </div>

        {{-- accent "credit card" hero — the single accent element --}}
        <div class="mt-5 relative overflow-hidden rounded-2xl px-5 pt-4 pb-4.5 text-white"
             style="background:linear-gradient(125deg, {{ $accent }} 0%, {{ $accent }}d9 55%, {{ $accent }}b3 100%); box-shadow:0 12px 28px -10px {{ $accent }}99">
            {{-- sheen --}}
            <div class="absolute -top-14 -right-10 w-48 h-48 rounded-full pointer-events-none" style="background:rgba(255,255,255,.12); filter:blur(2px)"></div>
            <div class="absolute -bottom-20 -left-8 w-44 h-44 rounded-full pointer-events-none" style="background:rgba(0,0,0,.12); filter:blur(2px)"></div>

            {{-- top row: chip + payment state --}}
            <div class="relative flex items-center justify-between">
                <span class="inline-block w-9 h-6.5 rounded-[5px]"
                      style="background:linear-gradient(135deg, #f3e3b8, #d9bd82); box-shadow:inset 0 0 0 1px rgba(0,0,0,.18); height:1.65rem"></span>
                @if($paymentRelevant)
                    <div class="text-right">
                        <p class="text-[10px] uppercase tracking-[0.1em] text-white/65">
                            {{ $b->balanceCents() === 0 ? 'Paid in full' : ($b->paid_cents > 0 ? 'Balance due' : 'Unpaid') }}</p>
                        <p class="text-[14px] font-bold tabular-nums leading-tight">
                            {{ $b->balanceCents() > 0 && $b->paid_cents > 0 ? $b->formattedBalance() : $b->formattedTotal() }}</p>
                    </div>
                @endif
            </div>

            {{-- "card number": the booking reference, spaced in groups --}}
            <p class="relative mt-4 font-mono text-[17px] font-semibold tracking-[0.18em] tabular-nums">
                {{ trim(chunk_split($b->reference, 4, ' ')) }}</p>

            {{-- bottom row: holder + valid line (cardholder / expiry treatment) --}}
            <div class="relative mt-3.5 flex items-end justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[9px] uppercase tracking-[0.14em] text-white/60">Customer</p>
                    <p class="text-[12.5px] font-semibold tracking-wide truncate uppercase">{{ $b->customer_name }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[9px] uppercase tracking-[0.14em] text-white/60">{{ $kind === 'stay' ? 'Dates' : ($kind === 'trip' ? 'Route' : 'When') }}</p>
                    <p class="text-[12.5px] font-semibold tracking-wide">{{ $when }}</p>
                    <p class="text-[10px] text-white/70">{{ $whenSub }}</p>
                </div>
            </div>
        </div>


        {{-- lifecycle box — Created → Confirmed → Paid / Cancelled --}}
        <div class="mt-4 rounded-xl px-4 py-3" style="background:rgba(236,229,216,.045); border:1px solid rgba(236,229,216,.08)">
            <div class="flex items-start">
                @foreach($b->timeline() as $i => $step)
                    @php $done = (bool) $step['at']; $bad = $step['label'] === 'Cancelled'; @endphp
                    @if($i > 0)
                        <div class="flex-1 h-px mt-[9px] mx-2" style="background:rgba(236,229,216,{{ $done ? '.35' : '.1' }})"></div>
                    @endif
                    <div class="flex flex-col items-center shrink-0">
                        <span class="w-[18px] h-[18px] rounded-full grid place-items-center text-[9px] font-bold"
                              style="{{ $bad && $done
                                  ? 'background:rgba(244,63,94,.18); color:#fda4af; box-shadow:inset 0 0 0 1px rgba(244,63,94,.5)'
                                  : ($done
                                      ? 'background:'.$accent.'; color:#fff; box-shadow:0 2px 6px -1px '.$accent.'99'
                                      : 'background:rgba(236,229,216,.07); color:#9a8f7e; box-shadow:inset 0 0 0 1px rgba(236,229,216,.18)') }}">
                            {{ $done ? ($bad ? '✕' : '✓') : $i + 1 }}
                        </span>
                        <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.07em] {{ $done ? ($bad ? 'text-rose-300' : 'text-[#ece5d8]') : 'text-[#7d7362]' }}">{{ $step['label'] }}</span>
                        <span class="text-[8.5px] tabular-nums {{ $done ? 'text-[#9a8f7e]' : 'text-[#5f574a]' }}">{{ $step['at']?->format('M j · g:i A') ?? '—' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- definition rows --}}
        <div class="mt-4 max-h-[26vh] overflow-y-auto">
            @foreach($rows as [$icon, $label, $value])
                <div class="flex items-center gap-3 py-[9px] border-b last:border-0" style="border-color:rgba(236,229,216,.06)">
                    <span class="shrink-0 text-[#8a7f6d]">{!! $icons[$icon] !!}</span>
                    <span class="shrink-0 text-[12px] text-[#9a8f7e]">{{ $label }}</span>
                    <span class="ml-auto text-right text-[13px] font-medium text-[#ece5d8] break-all leading-snug">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        {{-- total row (hidden when the service takes no payment) --}}
        @if($paymentRelevant)
        <div class="mt-4 pt-4 flex items-end justify-between gap-4 border-t" style="border-color:rgba(236,229,216,.1)">
            <div>
                <p class="text-[10.5px] font-semibold uppercase tracking-[0.1em] text-[#9a8f7e]">Total</p>
                @php
                    $cmeta = config('currencies.'.strtolower($b->currency ?: 'gbp'));
                    $sym = $cmeta['symbol'] ?? strtoupper($b->currency);
                    $after = ($cmeta['position'] ?? 'before') === 'after';
                @endphp
                <p class="mt-1 text-[28px] font-bold tracking-tight leading-none tabular-nums text-[#f6f1e7]">
                    @if($dec !== null && ! $after)<span class="text-[16px] font-semibold text-[#9a8f7e] mr-0.5">{{ $sym }}</span>@endif{{ $int }}@if($dec !== null)<span class="text-[15px] font-semibold text-[#9a8f7e]">.{{ $dec }}</span>@if($after)
                    <span class="text-[12px] font-semibold text-[#9a8f7e] ml-1">{{ $sym }}</span>@endif @endif
                </p>
            </div>
            @if($b->total_cents > 0 && $b->paid_cents > 0 && $b->balanceCents() > 0)
                <p class="text-[11px] text-[#9a8f7e] tabular-nums text-right leading-relaxed">paid {{ $b->formattedPaid() }}<br>due {{ $b->formattedBalance() }}</p>
            @endif
        </div>
        @endif
    </div>

    {{-- cream footer lip: actions --}}
    @if(trim($slot ?? '') !== '')
        <div class="px-2 pt-3.5 pb-2 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
