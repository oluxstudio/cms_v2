{{-- Double opt-in confirmation. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand['name'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                <tr>
                    <td style="padding:22px 32px;border-bottom:1px solid #eef0f3;">
                        @if ($brand['logo'])
                            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" height="36" style="height:36px;max-width:200px;display:block;">
                        @else
                            <span style="font-size:20px;font-weight:800;color:#111827;">{{ $brand['name'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 32px;font-size:15px;line-height:1.7;color:#374151;">
                        <p style="margin:0 0 12px;font-size:17px;font-weight:700;color:#111827;">{{ $name ? 'Hi '.$name.',' : 'Hi,' }}</p>
                        <p style="margin:0 0 20px;">Please confirm you'd like to receive emails from <strong>{{ $brand['name'] }}</strong>.</p>
                        <p style="margin:0 0 20px;">
                            <a href="{{ $confirmUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:10px;">Yes, subscribe me</a>
                        </p>
                        <p style="margin:0;font-size:13px;color:#6b7280;">If you didn't ask to subscribe, ignore this email and you won't hear from us again.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px 24px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                        {{ $brand['name'] }}@if ($brand['address']) · {{ $brand['address'] }}@endif
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
