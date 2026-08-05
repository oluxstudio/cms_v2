{{--
    Invoice card — dark, warm pricing-card treatment (One-Off card style):
    icon + title header, itemized list, big cream price + status chip.
    A soft glow of the site ACCENT keeps the "blue layer" present.
    Props: invoice (App\Models\Invoice), accent (hex).
--}}
{{-- variant: 'v1' (original) | 'v2' (solid primary glow circle) — pass v2 to test, drop the prop to revert. --}}
@props(['invoice', 'accent' => '#6366f1', 'variant' => 'v1'])
@php
    $fmt = fn (int $c) => \App\Support\Money::format($c, $invoice->currency);
    $statusChip = ['paid' => 'Paid ✓', 'sent' => 'Awaiting payment', 'overdue' => 'Overdue',
                   'draft' => 'Draft', 'cancelled' => 'Cancelled'][$invoice->status] ?? ucfirst($invoice->status);
    // Same rule as the booking card: no money involved → no payment section.
    $paymentRelevant = ($invoice->total_cents ?? 0) > 0;
@endphp
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl p-6 text-[#f4ead8]']) }}
     style="background:linear-gradient(165deg, #2b2723 0%, #17150f 100%); box-shadow:0 18px 40px rgba(0,0,0,.45)">
    {{-- the kept "blue layer": a soft accent glow + hairline --}}
    @if($variant === 'v2')
        {{-- V2: SOLID primary circle --}}
        <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full pointer-events-none" style="background:{{ $accent }}; opacity:.92"></div>
    @else
        <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full pointer-events-none" style="background:{{ $accent }}2e; filter:blur(6px)"></div>
    @endif
    <div class="absolute inset-x-0 top-0 h-px" style="background:linear-gradient(90deg, transparent, {{ $accent }}aa, transparent)"></div>


    {{-- header: icon + number + who --}}
    <div class="relative flex items-center gap-3.5">
        <span class="w-11 h-11 rounded-full grid place-items-center text-xl"
              style="background:rgba(244,234,216,.1); border:1px solid rgba(244,234,216,.25)">🧾</span>
        <div class="min-w-0">
            <p class="text-lg font-bold tracking-tight text-[#f7f1e3]">{{ $invoice->number }}</p>
            <p class="text-xs text-[#b8ac94]">Invoice for {{ $invoice->customer_name }}</p>
        </div>
        @unless($paymentRelevant)
            <span class="ml-auto shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-bold"
                  style="background:#efe3c6; color:#211d15; box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)">{{ $statusChip }}</span>
        @endunless
    </div>

	
        {{-- accent "lifecycle-box"  --}}
        <div class="mt-5 relative overflow-hidden rounded-2xl px-5 pt-4 pb-4.5 text-white"
             style="background:linear-gradient(125deg, {{ $accent }} 0%, {{ $accent }}d9 55%, {{ $accent }}b3 100%); box-shadow:0 12px 28px -10px {{ $accent }}99">
            {{-- sheen --}}
            <div class="absolute -top-14 -right-10 w-48 h-48 rounded-full pointer-events-none" style="background:rgba(255,255,255,.12); filter:blur(2px)"></div>
            <div class="absolute -bottom-20 -left-8 w-44 h-44 rounded-full pointer-events-none" style="background:rgba(0,0,0,.12); filter:blur(2px)"></div>
			
			

            {{-- lifecycle timeline — big steps (same as the invoice page) --}}
            <div class="relative flex items-start">
                @foreach($invoice->timeline() as $i => $step)
                    @php $done = (bool) $step['at']; @endphp
                    @if($i > 0)
                        <div class="flex-1 h-px mt-3.5 mx-1.5" style="background:rgba(255,255,255,{{ $done ? '.8' : '.25' }})"></div>
                    @endif
                    <div class="flex flex-col items-center shrink-0">
                        <span class="w-7 h-7 rounded-full grid place-items-center text-[11px] font-bold"
                              style="{{ $done
                                  ? 'background:#fff; color:'.$accent.'; box-shadow:0 2px 8px rgba(0,0,0,.25)'
                                  : 'background:rgba(255,255,255,.15); color:rgba(255,255,255,.7); box-shadow:inset 0 0 0 1px rgba(255,255,255,.35)' }}">
                            {{ $done ? '✓' : $i + 1 }}
                        </span>
                        <span class="mt-1.5 text-[9.5px] font-semibold uppercase tracking-[0.08em] {{ $done ? 'text-white' : 'text-white/55' }}">{{ $step['label'] }}</span>
                        <span class="text-[9px] tabular-nums {{ $done ? 'text-white/80' : 'text-white/35' }}">{{ $step['at']?->format('M j') ?? '—' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

    {{-- itemized list (feature-list treatment) --}}
    <div class="relative mt-6 space-y-3.5">
        @foreach((array) ($invoice->items ?? []) as $it)
            <div class="flex items-baseline gap-2.5">
                <span class="text-[13px] opacity-60">▫</span>
                <span class="text-[14px] font-medium">{{ $it['description'] ?? '' }}
                    @if(($it['qty'] ?? 1) > 1)<span class="text-[#b8ac94] font-normal"> × {{ $it['qty'] }}</span>@endif
                </span>
                <span class="ml-auto text-[13px] font-semibold tabular-nums text-[#e8dcbf]">{{ $fmt((int) ($it['unit_cents'] ?? 0) * (int) ($it['qty'] ?? 1)) }}</span>
            </div>
        @endforeach
        @if(($invoice->tax_cents ?? 0) > 0)
            <div class="flex items-baseline gap-2.5">
                <span class="text-[13px] opacity-60">▫</span>
                <span class="text-[14px] font-medium text-[#b8ac94]">Tax ({{ rtrim(rtrim(number_format($invoice->tax_bp / 100, 2), '0'), '.') }}%)</span>
                <span class="ml-auto text-[13px] font-semibold tabular-nums text-[#e8dcbf]">{{ $fmt($invoice->tax_cents) }}</span>
            </div>
        @endif
    </div>

    {{-- price row + status chip (hidden when no money is involved) --}}
    @if($paymentRelevant)
    <div class="relative flex items-end justify-between mt-7 pt-5" style="border-top:1px solid rgba(244,234,216,.12)">
        <div>
            <p class="text-3xl font-extrabold tracking-tight text-[#f3e6c8]">{{ $fmt($invoice->total_cents) }}</p>
            <p class="text-[11px] text-[#b8ac94] mt-0.5">
                @if($invoice->status === 'paid' && $invoice->paid_at) Paid {{ $invoice->paid_at->format('M j, Y') }}
                @elseif($invoice->due_date) Due {{ $invoice->due_date->format('M j, Y') }}
                @else Billed one time @endif
            </p>
        </div>
        <div class="px-3 py-1.5 rounded-lg text-[11px] font-bold my-10"
              style="background:#efe3c6; color:#211d15; box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)">{{ $statusChip }}</div>
    </div>
    @endif
</div>
