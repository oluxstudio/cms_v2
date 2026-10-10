{{-- Ticket confirmation + reminder email. Vars: site, siteLabel, logo, order, event, tickets, heading, intro. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <tr>
                <td style="padding:24px 32px;border-bottom:1px solid #eef0f3;">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $siteLabel }}" height="36" style="height:36px;max-width:200px;display:block;">
                    @else
                        <span style="font-size:20px;font-weight:800;color:#111827;">{{ $siteLabel }}</span>
                    @endif
                </td>
            </tr>
            @if($event->imageUrl())
            <tr><td><img src="{{ $event->imageUrl() }}" alt="" width="560" style="display:block;width:100%;max-height:240px;object-fit:cover;"></td></tr>
            @endif
            <tr>
                <td style="padding:26px 32px 6px;">
                    <p style="margin:0 0 6px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6366f1;">{{ $heading }}</p>
                    <h1 style="margin:0;font-size:22px;line-height:1.3;color:#111827;">{{ $event->title }}</h1>
                    <p style="margin:10px 0 0;font-size:15px;color:#374151;">Hi {{ $order->buyer_name }},</p>
                    @if($intro)
                        <p style="margin:8px 0 0;font-size:15px;line-height:1.6;color:#374151;">{{ $intro }}</p>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:14px 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;">
                        <tr><td style="padding:14px 16px;font-size:14px;line-height:1.7;color:#374151;">
                            <b>When:</b> {{ $event->whenLabel() }}<br>
                            @if($event->venueLabel())<b>Where:</b> {{ $event->venueLabel() }}<br>@endif
                            @if($event->online_url)<b>Online:</b> <a href="{{ $event->online_url }}" style="color:#4f46e5;">{{ $event->online_url }}</a><br>@endif
                            <b>Order:</b> {{ $order->reference }} · {{ $order->quantity }} {{ Str::plural('ticket', $order->quantity) }} · {{ $order->formattedTotal() }}
                        </td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 32px 8px;">
                    @foreach($tickets as $t)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px dashed #d1d5db;border-radius:12px;margin-bottom:10px;">
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;color:#374151;">
                                    <span style="display:block;font-weight:700;color:#111827;">{{ $t->attendee_name }}</span>
                                    <span style="display:block;font-size:12px;color:#6b7280;">{{ $t->ticketType?->name ?? 'Ticket' }}</span>
                                </td>
                                <td align="right" style="padding:12px 16px;">
                                    <span style="display:block;font-family:Menlo,Consolas,monospace;font-size:17px;font-weight:800;letter-spacing:.12em;color:#111827;">{{ $t->code }}</span>
                                    <a href="{{ $t->url($site->name) }}" style="font-size:12px;color:#4f46e5;">Show ticket &amp; QR code →</a>
                                </td>
                            </tr>
                        </table>
                    @endforeach
                </td>
            </tr>
            <tr>
                <td style="padding:6px 32px 20px;font-size:13px;line-height:1.6;color:#6b7280;">
                    Show the code or QR at the door. The attached <b>event.ics</b> adds this event to your calendar.
                    Questions? Just reply to this email.
                </td>
            </tr>
            <tr>
                <td style="padding:18px 32px 26px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">{{ $siteLabel }}</td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
