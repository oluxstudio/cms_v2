{{-- Newsletter campaign wrapper: site logo/name · body · footer with address + unsubscribe. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $brand['name'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
        <tr><td align="center">
            @if ($isTest ?? false)
                <p style="max-width:600px;margin:0 0 10px;font-size:12px;color:#92400e;background:#fef3c7;border-radius:10px;padding:8px 12px;">Test send — tracking and the unsubscribe link are inactive in this copy.</p>
            @endif
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                <tr>
                    <td style="padding:22px 32px;border-bottom:1px solid #eef0f3;">
                        @if ($brand['logo'])
                            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" height="36" style="height:36px;max-width:200px;display:block;">
                        @else
                            <span style="font-size:20px;font-weight:800;letter-spacing:-.01em;color:#111827;">{{ $brand['name'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 32px 30px;font-size:15px;line-height:1.7;color:#374151;">
                        {{-- Owner-authored HTML, sanitised by CampaignRenderer (no scripts / iframes / handlers). --}}
                        {!! $body !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px 26px;border-top:1px solid #eef0f3;font-size:12px;line-height:1.6;color:#9ca3af;">
                        <p style="margin:0 0 4px;">You're receiving this because you subscribed to {{ $brand['name'] }}.</p>
                        @if ($brand['address'])
                            <p style="margin:0 0 4px;">{{ $brand['name'] }} · {{ $brand['address'] }}</p>
                        @endif
                        @if ($unsubscribeUrl)
                            <p style="margin:0;"><a href="{{ $unsubscribeUrl }}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a></p>
                        @else
                            <p style="margin:0;color:#9ca3af;">Unsubscribe (link appears in real sends)</p>
                        @endif
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
