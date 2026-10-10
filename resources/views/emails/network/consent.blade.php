<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fromName }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <tr>
                <td style="padding:24px 32px;border-bottom:1px solid #eef0f3;">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $fromName }}" height="36" style="height:36px;max-width:200px;display:block;">
                    @else
                        <span style="font-size:20px;font-weight:800;letter-spacing:-.01em;color:#111827;">{{ $fromName }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:26px 32px 4px;font-size:16px;font-weight:600;color:#111827;">
                    Hi{{ $recipient ? ' '.$recipient : '' }},
                </td>
            </tr>
            <tr>
                <td style="padding:8px 32px 12px;font-size:15px;line-height:1.7;color:#374151;">
                    {{ $fromName }} would like to introduce you to <strong>{{ $toName }}</strong>, who can help with what you need.
                    We won't pass on any of your details unless you say yes.
                </td>
            </tr>
            <tr>
                <td style="padding:4px 32px 8px;">
                    <div style="border:1px solid #eef0f3;border-radius:14px;padding:14px 18px;font-size:14px;line-height:1.6;color:#4b5563;">
                        {{ $consentText }}
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 32px 26px;">
                    <a href="{{ $url }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:12px;">Yes or no — choose here</a>
                    <p style="margin:14px 0 0;font-size:12px;color:#9ca3af;line-height:1.6;">This link is just for you and works for {{ $days }} days. If you ignore it, nothing is shared.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:20px 32px 28px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                    Thank you — {{ $fromName }} · Sent via the Olux Referral Network
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
