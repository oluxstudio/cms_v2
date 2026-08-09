<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to Olux Studio</title>
</head>
<body style="margin:0;padding:0;background:#f2efe8;font-family:Arial,Helvetica,sans-serif;color:#332433;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2efe8;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px -12px rgba(0,0,0,.2);">
                    <tr>
                        <td style="background:linear-gradient(120deg,#e38704,#f77315);padding:34px 40px;">
                            <p style="margin:0;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.85);">Olux Studio</p>
                            <h1 style="margin:6px 0 0;font-size:26px;color:#ffffff;">You're all set{{ $planName ? ' on '.$planName : '' }} 🎉</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 40px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hi {{ $name }},</p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Thanks for joining Olux Studio — your account is active and ready to go.
                                We put together a short getting-started guide that walks you through building
                                your first site, capturing leads, taking payments, and going live.
                            </p>
                            <p style="margin:0 0 28px;font-size:16px;line-height:1.6;">
                                It takes about 20 minutes end to end.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:12px;background:linear-gradient(120deg,#e38704,#f77315);">
                                        <a href="{{ $tutorialUrl }}" style="display:inline-block;padding:14px 40px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;">Read the tutorial</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:28px 0 0;font-size:14px;line-height:1.6;color:#6b6470;">
                                Prefer to dive straight in? <a href="{{ $dashboardUrl }}" style="color:#f77315;">Go to your dashboard</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px;border-top:1px solid #eee;">
                            <p style="margin:0;font-size:12px;color:#9a94a1;">You're receiving this because you activated a plan on Olux Studio.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
