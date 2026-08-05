<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    <style>
        :root { color-scheme: light; --accent: {{ $site->themeValues()['accent'] ?? '#4f46e5' }}; --font: {{ $site->themeValues()['font'] ?? 'Inter' }}, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; margin: 0; }
        body { font-family: var(--font); background: #f3f4f8; color: #111827; padding: 24px 16px; }
        .brand { text-align: center; margin-bottom: 18px; }
        .brand h2 { font-size: 17px; font-weight: 800; color: var(--accent); }
        .brand p { font-size: 12px; color: #6b7280; }
        .portal-link { display: block; text-align: center; margin-top: 12px; font-size: 12.5px; color: var(--accent); font-weight: 600; text-decoration: none; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 18px; border: 1px solid #e5e7eb; padding: 28px; }
        .head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 20px; }
        h1 { font-size: 20px; font-weight: 800; }
        .muted { color: #6b7280; font-size: 13px; }
        .badge { font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; }
        .badge.paid { background: #d1fae5; color: #047857; }
        .badge.sent, .badge.draft { background: color-mix(in srgb, var(--accent) 14%, white); color: var(--accent); }
        .badge.overdue { background: #fee2e2; color: #b91c1c; }
        .badge.cancelled { background: #f3f4f6; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; padding: 8px 4px; border-bottom: 1px solid #e5e7eb; }
        th:last-child, td:last-child { text-align: right; }
        td { padding: 10px 4px; border-bottom: 1px solid #f3f4f6; }
        .totals { margin-left: auto; width: 240px; font-size: 14px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-weight: 800; font-size: 17px; border-top: 2px solid #111827; margin-top: 6px; padding-top: 10px; }
        .pay { display: block; width: 100%; text-align: center; margin-top: 22px; padding: 14px; border: 0; border-radius: 12px; background: var(--accent); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .notes { margin-top: 18px; padding: 12px 14px; background: #f9fafb; border-radius: 10px; font-size: 13px; color: #4b5563; }
        .footer { text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="brand">
        <h2>{{ ucwords(str_replace('-', ' ', $site->name)) }}</h2>
        @if($site->description)<p>{{ $site->description }}</p>@endif
    </div>
    <div class="card">
        <div class="head">
            <div>
                <h1>Invoice {{ $invoice->number }}</h1>
                <p class="muted">{{ ucwords(str_replace('-', ' ', $site->name)) }}</p>
            </div>
            <span class="badge {{ $invoice->status }}">{{ $invoice->status }}</span>
        </div>

        <p class="muted">Billed to</p>
        <p style="font-weight:700">{{ $invoice->customer_name }}</p>
        <p class="muted">{{ $invoice->customer_email }}</p>
        @if($invoice->due_date)
            <p class="muted" style="margin-top:6px">Due {{ $invoice->due_date->format('F j, Y') }}</p>
        @endif

        <table>
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Amount</th></tr></thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td style="text-align:right">{{ \App\Support\Money::format((int) $item['unit_cents'], $invoice->currency) }}</td>
                        <td>{{ \App\Support\Money::format((int) ($item['qty'] * $item['unit_cents']), $invoice->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span class="muted">Subtotal</span><span>{{ \App\Support\Money::format((int) $invoice->subtotal_cents, $invoice->currency) }}</span></div>
            @if($invoice->tax_cents > 0)
                <div><span class="muted">Tax ({{ $invoice->tax_bp / 100 }}%)</span><span>{{ \App\Support\Money::format((int) $invoice->tax_cents, $invoice->currency) }}</span></div>
            @endif
            <div class="grand"><span>Total</span><span>{{ $invoice->formattedTotal() }}</span></div>
        </div>

        @if($invoice->status === 'paid')
            <p class="notes" style="background:#d1fae5;color:#047857;font-weight:600">
                Paid {{ $invoice->paid_at?->format('F j, Y') }} — thank you!
            </p>
        @elseif($invoice->isPayable() && $site->stripeReady())
            <form method="POST" action="{{ route('public.invoice.pay', [$site->name, $invoice->public_token]) }}">
                @csrf
                <button type="submit" class="pay">Pay {{ $invoice->formattedTotal() }}</button>
            </form>
        @endif

        @if($invoice->notes)
            <div class="notes">{{ $invoice->notes }}</div>
        @endif

        <a href="{{ route('public.invoice.pdf', [$site->name, $invoice->public_token]) }}"
           style="display:block; text-align:center; margin-top:12px; font-size:13px; font-weight:700; color:var(--accent); text-decoration:none">⬇ Download PDF</a>
    </div>
    <a class="portal-link" href="{{ $invoice->portalUrl() }}">View all your invoices →</a>
    <p class="footer muted">Powered by {{ ucwords(str_replace('-', ' ', $site->name)) }}</p>
</body>
</html>
