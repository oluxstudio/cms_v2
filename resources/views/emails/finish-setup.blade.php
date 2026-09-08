<!DOCTYPE html>
<html>
<body style="margin:0;padding:32px 16px;background:#f7f5f0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #eee">
        <h1 style="font-size:20px;margin:0 0 12px">Hi {{ $user->name }},</h1>
        <p style="font-size:15px;line-height:1.6;margin:0 0 16px">
            @if ($step <= 2)
                You created your Olux account but haven’t set up your site yet. It takes about two minutes — tell us your business name and we build the pages, booking and tools for you.
            @else
                {{ $business ?: 'Your site' }} is built and waiting — confirm your email and you're into your dashboard.
            @endif
        </p>
        <p style="margin:24px 0">
            <a href="{{ route('start') }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;font-size:14px">Finish setting up →</a>
        </p>
        <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0">Your {{ config('plans.trial_days', 14) }}-day free trial is still available — no card needed. If you didn’t sign up, you can ignore this email.</p>
    </div>
</body>
</html>
