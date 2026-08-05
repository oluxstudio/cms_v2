<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Your invoices — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    <style>
        :root { color-scheme: light; --accent: {{ $site->themeValues()['accent'] ?? '#4f46e5' }}; --font: {{ $site->themeValues()['font'] ?? 'Inter' }}, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; margin: 0; }
        body { font-family: var(--font); background: #f3f4f8; color: #111827; padding: 24px 16px; }
        .brand { text-align: center; margin-bottom: 18px; }
        .brand h2 { font-size: 17px; font-weight: 800; color: var(--accent); }
        .brand p { font-size: 12px; color: #6b7280; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 18px; border: 1px solid #e5e7eb; padding: 24px; }
        h1 { font-size: 18px; font-weight: 800; margin-bottom: 2px; }
        .muted { color: #6b7280; font-size: 13px; }
        .row { display: flex; align-items: center; gap: 12px; padding: 14px 4px; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: 0; }
        .num { font-weight: 700; font-size: 14px; }
        .amt { margin-left: auto; font-weight: 800; font-size: 14px; white-space: nowrap; }
        .badge { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
        .badge.paid { background: #d1fae5; color: #047857; }
        .badge.sent { background: color-mix(in srgb, var(--accent) 14%, white); color: var(--accent); }
        .badge.overdue { background: #fee2e2; color: #b91c1c; }
        .badge.cancelled { background: #f3f4f6; color: #6b7280; }
        .pay { font-size: 12px; font-weight: 700; color: #fff; background: var(--accent); border-radius: 8px; padding: 6px 12px; text-decoration: none; white-space: nowrap; }
        .view { font-size: 12px; font-weight: 600; color: var(--accent); text-decoration: none; white-space: nowrap; }
        .footer { text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="brand">
        <h2>{{ ucwords(str_replace('-', ' ', $site->name)) }}</h2>
        @if($site->description)<p>{{ $site->description }}</p>@endif
    </div>
    <div class="card">
        <h1>Your invoices</h1>
        <p class="muted">{{ $invoice->customer_name }} · {{ $invoice->customer_email }}</p>
        <div style="margin-top:10px">
            @forelse($invoices as $inv)
                <div class="row">
                    <div>
                        <p class="num">{{ $inv->number }}</p>
                        <p class="muted">
                            @if($inv->status === 'paid' && $inv->paid_at) Paid {{ $inv->paid_at->format('M j, Y') }}
                            @elseif($inv->due_date) Due {{ $inv->due_date->format('M j, Y') }}
                            @else {{ $inv->created_at->format('M j, Y') }} @endif
                        </p>
                    </div>
                    <span class="badge {{ $inv->status }}">{{ $inv->status }}</span>
                    <span class="amt">{{ $inv->formattedTotal() }}</span>
                    @if($inv->isPayable() && $site->stripeReady())
                        <a class="pay" href="{{ $inv->payUrl() }}">Pay now</a>
                    @else
                        <a class="view" href="{{ $inv->payUrl() }}">View</a>
                    @endif
                    <a class="view" href="{{ route('public.invoice.pdf', [$site->name, $inv->public_token]) }}" title="Download PDF">PDF</a>
                </div>
            @empty
                <p class="muted" style="padding:18px 0">No invoices yet.</p>
            @endforelse
        </div>
    </div>
    <p class="footer muted">Powered by {{ ucwords(str_replace('-', ' ', $site->name)) }}</p>
</body>
</html>
