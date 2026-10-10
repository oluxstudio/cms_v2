<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <tr>
                <td style="padding:24px 32px;border-bottom:1px solid #eef0f3;">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $siteName }}" height="36" style="height:36px;max-width:200px;display:block;">
                    @else
                        <span style="font-size:20px;font-weight:800;letter-spacing:-.01em;color:#111827;">{{ $siteName }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:26px 32px 4px;font-size:16px;font-weight:600;color:#111827;">
                    Hi{{ $recipient ? ' '.$recipient : '' }},
                </td>
            </tr>
            @if($reminder)
            <tr>
                <td style="padding:8px 32px 0;font-size:15px;line-height:1.7;color:#374151;">
                    Just a gentle reminder — we'd still love to hear how we did.
                </td>
            </tr>
            @endif
            <tr>
                <td style="padding:8px 32px 12px;font-size:15px;line-height:1.7;color:#374151;">
                    {!! nl2br(e($ask)) !!}
                </td>
            </tr>
            <tr>
                <td style="padding:6px 32px 8px;font-size:28px;letter-spacing:4px;color:#f59e0b;">★★★★★</td>
            </tr>
            <tr>
                <td style="padding:8px 32px 26px;">
                    <a href="{{ $url }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:12px;">Leave a review</a>
                    <p style="margin:14px 0 0;font-size:12px;color:#9ca3af;line-height:1.6;">It takes about a minute. This link is just for you and works once.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:20px 32px 28px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                    Thank you — {{ $siteName }}
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
