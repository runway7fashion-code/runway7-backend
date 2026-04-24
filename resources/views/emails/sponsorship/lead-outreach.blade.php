<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Runway 7 Fashion</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background:#f0f0f0; color:#1a1a1a; }
        .wrap { padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background:#000; padding: 24px 40px; text-align:center; }
        .header img { display:block; margin:0 auto; }
        .body { padding: 32px 40px; font-size:15px; line-height:1.7; color:#333; }
        .body p { margin: 0 0 12px 0; }
        .body ul, .body ol { margin: 0 0 12px 0; padding-left: 24px; }
        .body a { color:#D4AF37; text-decoration: underline; }
        .body img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }
        .body hr { border: 0; border-top: 1px solid #e5e7eb; margin: 12px 0; }
        .signature { margin-top: 28px; padding-top: 18px; border-top: 1px solid #eee; font-size: 13px; color: #444; line-height: 1.65; }
        .signature .name { font-size: 14px; font-weight: 700; color: #111; }
        .signature .title { font-size: 12px; color: #666; margin-bottom: 6px; }
        .signature a { color: #D4AF37; text-decoration: none; }
        .footer { background:#000; padding:20px 40px; text-align:center; color:#888; font-size:12px; }
        .footer a { color:#D4AF37; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="header">
            <img src="{{ url('/images/logo-email.png') }}" alt="Runway 7 Fashion" width="100" height="100" />
        </div>
        <div class="body">{!! $bodyText !!}<div class="signature">
            <div class="name">Christina Kovacs</div>
            <div class="title">Sponsors Relations Director</div>
            Phone: +1 (646)-480-9431<br>
            Web: <a href="https://runway7fashion.com">https://runway7fashion.com</a><br>
            Instagram: <a href="https://www.instagram.com/runway7fashion/">https://www.instagram.com/runway7fashion/</a><br>
            Address: 10 Times Square, FL 5 New York City, NY 10018
        </div></div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Runway 7 Fashion. <a href="https://runway7fashion.com">runway7fashion.com</a></p>
        </div>
    </div>
</div>
</body>
</html>
