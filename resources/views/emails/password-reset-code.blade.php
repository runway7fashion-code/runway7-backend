<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Password reset</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;">
    <div style="max-width:520px;margin:40px auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #eee;">
        <div style="background:#000;padding:24px;text-align:center;">
            <h1 style="color:#D4AF37;font-size:24px;letter-spacing:4px;margin:0;">RUNWAY 7</h1>
        </div>

        <div style="padding:32px 28px;">
            <p style="font-size:15px;color:#333;margin:0 0 16px;">Hi{{ $firstName ? ' '.$firstName : '' }},</p>

            <p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 24px;">
                We received a request to reset the password for your Runway7 account. Use the code below to continue — it will expire in <strong>15 minutes</strong>.
            </p>

            <div style="background:#f8f8f8;border:1px solid #eee;border-radius:12px;padding:20px;text-align:center;margin-bottom:24px;">
                <div style="font-size:36px;font-weight:700;letter-spacing:12px;color:#000;font-family:'Courier New',monospace;">
                    {{ $code }}
                </div>
            </div>

            <p style="font-size:13px;color:#777;line-height:1.5;margin:0 0 12px;">
                If you didn't request this, you can safely ignore this email — your password will remain unchanged.
            </p>

            <p style="font-size:13px;color:#999;line-height:1.5;margin:24px 0 0;">
                — The Runway7 team
            </p>
        </div>

        <div style="background:#fafafa;padding:14px;text-align:center;font-size:11px;color:#aaa;border-top:1px solid #eee;">
            Runway7 Fashion · New York
        </div>
    </div>
</body>
</html>
