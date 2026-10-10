<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New review — {{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <tr>
                <td style="padding:24px 32px 6px;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#be123c;">Awaiting approval</td>
            </tr>
            <tr>
                <td style="padding:0 32px 8px;font-size:20px;font-weight:800;color:#111827;">A new review for {{ $siteName }}</td>
            </tr>
            <tr>
                <td style="padding:8px 32px;">
                    <div style="border:1px solid #eef0f3;border-radius:14px;padding:18px 20px;">
                        <div style="font-size:20px;letter-spacing:3px;color:#f59e0b;">{{ str_repeat('★', $review->rating) }}<span style="color:#d1d5db;">{{ str_repeat('★', 5 - $review->rating) }}</span></div>
                        @if($review->title)
                            <div style="margin-top:8px;font-size:16px;font-weight:700;color:#111827;">{{ $review->title }}</div>
                        @endif
                        <div style="margin-top:6px;font-size:14px;line-height:1.7;color:#374151;">{!! nl2br(e(\Illuminate\Support\Str::limit($review->body, 600))) !!}</div>
                        <div style="margin-top:10px;font-size:12px;color:#9ca3af;">— {{ $review->name }} · {{ \App\Modules\Reviews\Models\Review::SOURCES[$review->source] ?? $review->source }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding:14px 32px 26px;">
                    <a href="{{ $url }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 24px;border-radius:12px;">Review &amp; approve</a>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 32px 26px;border-top:1px solid #eef0f3;font-size:12px;color:#9ca3af;">
                    Reviews below your auto-publish setting wait for you. Change it in the Marketplace settings for Reviews & Testimonials.
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
