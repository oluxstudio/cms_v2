<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your verification code</title>
</head>
<body style="margin:0;padding:0;background:#f2efe8;font-family:Arial,Helvetica,sans-serif;color:#332433;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2efe8;padding:32px 0;">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px -12px rgba(0,0,0,.2);">
                <tr>
                    <td style="background:linear-gradient(120deg,#4f46e5,#7c3aed);padding:28px 40px;">
                        <p style="margin:0;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.85);">Olux Studio</p>
                        <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;">Verify your email</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 40px;">
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Enter this code to finish creating your account:</p>
                        <div style="margin:0 auto 18px;text-align:center;">
                            <span style="display:inline-block;font-size:34px;font-weight:bold;letter-spacing:10px;color:#4f46e5;background:#f3f0ff;border-radius:12px;padding:16px 24px;">{{ $code }}</span>
                        </div>
                        <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#6b6470;">This code expires in {{ $ttl }} minutes. If you didn't request it, you can safely ignore this email — no account will be created.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 40px;border-top:1px solid #eee;">
                        <p style="margin:0;font-size:12px;color:#9a94a1;">Never share this code. Olux will never ask you for it.</p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
