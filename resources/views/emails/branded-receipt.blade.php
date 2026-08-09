@php
    $siteName = ucwords(str_replace('-', ' ', $site->name));
@endphp
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

                {{-- Header: site logo + name --}}
                <tr>
                    <td style="padding:24px 32px;border-bottom:1px solid #eef0f3;">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $siteName }}" height="36" style="height:36px;max-width:200px;display:block;">
                        @else
                            <span style="font-size:20px;font-weight:800;letter-spacing:-.01em;color:#111827;">{{ $siteName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Body: the admin-editable copy --}}
                <tr>
                    <td style="padding:28px 32px 8px;font-size:15px;line-height:1.7;color:#374151;">
                        {!! nl2br(e($body)) !!}
                    </td>
                </tr>

                {{-- Optional summary of what was submitted --}}
                @if(!empty($summary))
                <tr>
                    <td style="padding:8px 32px 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;">
                            <tr><td style="padding:14px 16px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;">What you sent</td></tr>
                            @foreach($summary as $label => $value)
                            <tr>
                                <td style="padding:4px 16px;font-size:13px;color:#374151;">
                                    <strong style="color:#111827;">{{ \Illuminate\Support\Str::headline((string) $label) }}:</strong>
                                    {{ is_array($value) ? implode(', ', $value) : $value }}
                                </td>
                            </tr>
                            @endforeach
                            <tr><td style="height:12px;"></td></tr>
                        </table>
                    </td>
                </tr>
                @endif

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 32px 28px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                        This message was sent by {{ $siteName }}. No action is needed — it's a copy for your records.
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
