{{-- Organiser → attendees message (and cancellation notices). Vars: site, siteLabel, logo, event, body, name. --}}
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
            <tr>
                <td style="padding:26px 32px 8px;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6366f1;">{{ $event->title }}</p>
                    <p style="margin:0;font-size:13px;color:#6b7280;">{{ $event->whenLabel() }}</p>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 32px 24px;font-size:15px;line-height:1.7;color:#374151;">
                    @if($name)<p style="margin:0 0 10px;">Hi {{ $name }},</p>@endif
                    {!! nl2br(e($body)) !!}
                </td>
            </tr>
            <tr>
                <td style="padding:18px 32px 26px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">{{ $siteLabel }} · reply to this email to get in touch</td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
