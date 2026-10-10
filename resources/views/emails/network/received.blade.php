<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New referral — {{ $toName }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <tr>
                <td style="padding:24px 32px 6px;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;">New referral · {{ $referral->reference }}</td>
            </tr>
            <tr>
                <td style="padding:0 32px 8px;font-size:20px;font-weight:800;color:#111827;">{{ $fromName }} sent you a customer</td>
            </tr>
            <tr>
                <td style="padding:8px 32px;">
                    <div style="border:1px solid #eef0f3;border-radius:14px;padding:18px 20px;font-size:14px;line-height:1.7;color:#374151;">
                        <div style="font-size:16px;font-weight:700;color:#111827;">{{ $referral->customer_name }}</div>
                        <div>{{ $referral->customer_email }}</div>
                        @if($referral->customer_phone)<div>{{ $referral->customer_phone }}</div>@endif
                        @if($referral->note)
                            <div style="margin-top:10px;color:#4b5563;">{!! nl2br(e(\Illuminate\Support\Str::limit($referral->note, 800))) !!}</div>
                        @endif
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 32px 0;font-size:14px;line-height:1.7;color:#4b5563;">
                    They agreed to be introduced and are now in your contacts. Accept the referral and get in touch.
                    If they pay you within {{ $days }} days, your referral fee of {{ $fee }} goes on your next Olux bill.
                </td>
            </tr>
            <tr>
                <td style="padding:16px 32px 26px;">
                    <a href="{{ $url }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 24px;border-radius:12px;">View referral</a>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 32px 26px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                    Olux Referral Network — local businesses passing work to each other.
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
