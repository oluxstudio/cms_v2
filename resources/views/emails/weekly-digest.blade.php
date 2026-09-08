<!DOCTYPE html>
<html>
<body style="margin:0;padding:32px 16px;background:#f7f5f0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #eee">
        <h1 style="font-size:20px;margin:0 0 4px">Your week at {{ $siteName }}</h1>
        <p style="font-size:13px;color:#6b7280;margin:0 0 20px">{{ $stats['since']->format('j M') }} – {{ now()->format('j M Y') }}</p>

        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6">Bookings held</td><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700">{{ $stats['bookings_held'] }}</td></tr>
            <tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6">Coming up</td><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700">{{ $stats['bookings_upcoming'] }}</td></tr>
            <tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6">Money collected</td><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700">£{{ number_format($stats['revenue_cents'] / 100, 2) }}</td></tr>
            <tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6">New leads</td><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700">{{ $stats['new_leads'] }}</td></tr>
            @if ($stats['overdue_count'])
            <tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;color:#b91c1c">Invoices overdue</td><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700;color:#b91c1c">{{ $stats['overdue_count'] }} · £{{ number_format($stats['overdue_cents'] / 100, 2) }}</td></tr>
            @endif
            @if ($stats['reviews_requested'])
            <tr><td style="padding:8px 0">Review requests sent</td><td style="padding:8px 0;text-align:right;font-weight:700">{{ $stats['reviews_requested'] }}</td></tr>
            @endif
        </table>

        @if ($stats['highlights'])
            <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin:22px 0 8px">Highlights</p>
            @foreach ($stats['highlights'] as $line)
                <p style="font-size:13px;line-height:1.5;margin:0 0 6px">• {{ $line }}</p>
            @endforeach
        @endif

        <p style="margin:26px 0 0">
            <a href="{{ $dashboardUrl }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;font-size:14px">Open your dashboard →</a>
        </p>
        <p style="font-size:11px;color:#9ca3af;line-height:1.6;margin:18px 0 0">You get this every Monday. Turn it off from your site's settings (weekly digest).</p>
    </div>
</body>
</html>
