<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment received — {{ ucwords(str_replace('-', ' ', $site->name)) }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f3f4f8; color: #111827; display: grid; place-items: center; min-height: 100vh; padding: 16px; }
        .card { max-width: 420px; text-align: center; background: #fff; border-radius: 18px; border: 1px solid #e5e7eb; padding: 36px 28px; }
        .check { width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: #d1fae5; color: #059669; font-size: 26px; display: grid; place-items: center; }
        h1 { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
        p { color: #6b7280; font-size: 14px; }
        a { display: inline-block; margin-top: 18px; color: #4f46e5; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="check">✓</div>
        <h1>Payment received</h1>
        <p>Invoice <strong>{{ $invoice->number }}</strong> — {{ $invoice->formattedTotal() }}.</p>
        <p>A receipt has been sent to {{ $invoice->customer_email }}.</p>
        <a href="{{ route('public.invoice', [$site->name, $invoice->public_token]) }}">View invoice</a>
    </div>
</body>
</html>
