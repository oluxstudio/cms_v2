<!DOCTYPE html>
{{-- Invoice PDF — professional studio letterhead (Sempurna-style):
     accent header block (brand · big INVOICE · date/number/bill-to/total-due),
     white body with pill-row item table, thank-you + payment methods
     (online / phone / bank transfer) + terms, subtotal/tax and an accent
     Total pill. Dompdf-safe: tables + solid colors, DejaVu fonts.

     Optional site settings (invoices feature config) surface here:
       payment_phone · bank_name · bank_account · sort_code · terms --}}
@php
    $accent = $site->theme['accent'] ?? '#4f46e5';
    $accentDark = '#1f2430';
    $name = ucwords(str_replace('-', ' ', $site->name));
    $fmt = fn (int $c) => \App\Support\Money::format($c, $invoice->currency);
    $cfg = $site->feature('invoices') ?? [];
    $phone = $cfg['payment_phone'] ?? null;
    $bankName = $cfg['bank_name'] ?? null;
    $bankAccount = $cfg['bank_account'] ?? null;
    $sortCode = $cfg['sort_code'] ?? null;
    $terms = $cfg['terms'] ?? 'Payment is due by the date shown. Late payments may incur a reminder. Please reference the invoice number with your payment.';
    $items = (array) ($invoice->items ?? []);
    $due = $invoice->status === 'paid' ? 0 : (int) $invoice->total_cents;
@endphp
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; color: #2a2f3a; background: #eef0f4; }

    /* ── accent header ── */
    .band { background: {{ $accent }}; color: #fff; padding: 36px 42px 54px; }
    .band table { width: 100%; border-collapse: collapse; }
    .brand-name { font-size: 17px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
    .brand-sub { font-size: 8.5px; letter-spacing: 3px; text-transform: uppercase; opacity: .8; margin-top: 2px; }
    .h-invoice { font-size: 42px; font-weight: bold; letter-spacing: 2px; margin-top: 26px; line-height: 1; }
    .h-number { font-size: 13px; font-weight: bold; opacity: .9; margin-top: 6px; }
    .h-issued { font-size: 10px; opacity: .8; margin-top: 22px; }
    .meta p.k { font-size: 10.5px; font-weight: bold; margin-top: 12px; }
    .meta p.v { font-size: 9.5px; opacity: .85; line-height: 1.55; }
    .meta .total-due { font-size: 11px; font-weight: bold; margin-top: 16px; }

    /* ── white body card overlapping the band ── */
    .sheet { background: #ffffff; border-radius: 16px; margin: -30px 26px 0; padding: 26px 28px 30px; }

    /* item table */
    .items { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
    .items thead th { background: #e9ebf0; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #3a4150; padding: 9px 12px; text-align: left; }
    .items thead th:first-child { border-radius: 9px 0 0 9px; }
    .items thead th:last-child { border-radius: 0 9px 9px 0; }
    .items th.r, .items td.r { text-align: right; }
    .items tbody td { padding: 10px 12px; font-size: 11px; vertical-align: top; }
    .items tbody tr.alt td { background: #f1f2f6; }
    .items tbody tr.alt td:first-child { border-radius: 9px 0 0 9px; }
    .items tbody tr.alt td:last-child { border-radius: 0 9px 9px 0; }
    .no { font-weight: bold; color: #3a4150; width: 34px; }
    .idesc { font-weight: bold; color: #1f2430; }
    .isub { font-size: 9px; color: #8a90a0; margin-top: 2px; }

    /* footer columns */
    .foot { width: 100%; border-collapse: collapse; margin-top: 22px; }
    .foot td { vertical-align: top; }
    .thanks { font-size: 14px; font-weight: bold; color: #1f2430; }
    .contact { font-size: 9.5px; color: #5a6070; margin-top: 6px; line-height: 1.7; }
    .sec { font-size: 9.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #1f2430; margin-top: 16px; }
    .sec-body { font-size: 9.5px; color: #5a6070; line-height: 1.7; margin-top: 4px; }
    .pay-k { font-weight: bold; color: #3a4150; }

    .sums td { padding: 4px 12px; font-size: 10.5px; color: #5a6070; }
    .sums td.r { text-align: right; font-weight: bold; color: #2a2f3a; }
    .total-pill { background: {{ $accent }}; border-radius: 10px; }
    .total-pill td { color: #ffffff; font-weight: bold; font-size: 13px; padding: 11px 14px; }
    .paid-note { font-size: 9.5px; color: #10b981; font-weight: bold; text-align: right; padding: 6px 12px 0; }

    .sign { text-align: center; margin-top: 26px; }
    .sign .who { font-size: 11.5px; font-weight: bold; color: #1f2430; }
    .sign .role { font-size: 9px; color: #8a90a0; }
    .rule { height: 1px; background: #e2e5ec; margin: 22px 0 0; }
    .bottom { text-align: center; font-size: 8.5px; color: #9aa0ad; padding: 14px 30px 26px; }
</style>
</head>
<body>

    {{-- ── header band ── --}}
    <div class="band">
        <table>
            <tr>
                <td width="58%">
                    <p class="brand-name">{{ $name }}</p>
                    @if($site->description)<p class="brand-sub">{{ \Illuminate\Support\Str::limit($site->description, 60) }}</p>@endif
                    <p class="h-invoice">INVOICE</p>
                    <p class="h-number"># {{ $invoice->number }}</p>
                    <p class="h-issued">{{ $name }}, {{ $invoice->created_at->format('d F Y') }}</p>
                </td>
                <td class="meta">
                    <p class="k">Date Information</p>
                    <p class="v">Issued {{ $invoice->created_at->format('d / m / Y') }}@if($invoice->due_date)<br>Due {{ $invoice->due_date->format('d / m / Y') }}@endif</p>
                    <p class="k">Invoice Number</p>
                    <p class="v">{{ $invoice->number }}</p>
                    <p class="k">Invoice to:</p>
                    <p class="v">{{ $invoice->customer_name }}<br>{{ $invoice->customer_email }}</p>
                    <p class="total-due">Total Due: {{ $fmt($due) }}@if($invoice->status === 'paid') — PAID @endif</p>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── white sheet ── --}}
    <div class="sheet">
        <table class="items">
            <thead>
                <tr>
                    <th width="40">No.</th>
                    <th>Item Description</th>
                    <th class="r" width="90">Price</th>
                    <th class="r" width="50">Qty.</th>
                    <th class="r" width="100">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $it)
                    @php $qty = max(1, (int) ($it['qty'] ?? 1)); $unit = (int) ($it['unit_cents'] ?? 0); @endphp
                    <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
                        <td class="no">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}.</td>
                        <td><p class="idesc">{{ $it['description'] ?? '' }}</p></td>
                        <td class="r">{{ $fmt($unit) }}</td>
                        <td class="r">{{ $qty }}</td>
                        <td class="r"><b>{{ $fmt($qty * $unit) }}</b></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- footer: thank-you + payment methods | sums + total pill --}}
        <table class="foot">
            <tr>
                <td width="55%" style="padding-right:24px">
                    <p class="thanks">Thank you for your business</p>
                    <p class="contact">
                        ✉ {{ $site->user?->email ?? '' }}
                        @if($phone)<br>✆ {{ $phone }}@endif
                    </p>

                    <p class="sec">Payment Methods</p>
                    <p class="sec-body">
                        <span class="pay-k">1 · Pay online (card):</span> {{ $invoice->payUrl() }}<br>
                        @if($phone)<span class="pay-k">2 · Pay by phone:</span> call {{ $phone }} with your invoice number<br>@endif
                        <span class="pay-k">{{ $phone ? '3' : '2' }} · Bank transfer:</span>
                        @if($bankAccount)
                            {{ $bankName ? $bankName.' · ' : '' }}Account {{ $bankAccount }}{{ $sortCode ? ' · Sort code '.$sortCode : '' }} — reference {{ $invoice->number }}
                        @else
                            contact us for bank details, and reference {{ $invoice->number }}
                        @endif
                    </p>

                    <p class="sec">Terms &amp; Conditions</p>
                    <p class="sec-body">{{ $terms }}</p>
                </td>
                <td>
                    <table width="100%" class="sums">
                        <tr><td>Sub total</td><td width="18" style="text-align:center">:</td><td class="r">{{ $fmt((int) $invoice->subtotal_cents) }}</td></tr>
                        @if(($invoice->tax_cents ?? 0) > 0)
                            <tr><td>Tax ({{ rtrim(rtrim(number_format($invoice->tax_bp / 100, 2), '0'), '.') }}%)</td><td style="text-align:center">:</td><td class="r">{{ $fmt((int) $invoice->tax_cents) }}</td></tr>
                        @endif
                    </table>
                    <table width="100%" class="total-pill" style="margin-top:8px">
                        <tr><td>Total</td><td width="18" style="text-align:center">:</td><td style="text-align:right">{{ $fmt((int) $invoice->total_cents) }}</td></tr>
                    </table>
                    @if($invoice->status === 'paid')
                        <p class="paid-note">✓ Paid {{ $invoice->paid_at?->format('d M Y') }} — nothing due</p>
                    @endif

                    <div class="sign">
                        <p class="who">{{ $site->user?->name ?? $name }}</p>
                        <p class="role">{{ $name }}</p>
                    </div>
                </td>
            </tr>
        </table>

        @if($invoice->notes)
            <div class="rule"></div>
            <p class="sec" style="margin-top:14px">Notes</p>
            <p class="sec-body">{{ $invoice->notes }}</p>
        @endif
    </div>

    <p class="bottom">{{ $name }} · Invoice {{ $invoice->number }} · Pay online: {{ $invoice->payUrl() }}</p>

</body>
</html>
