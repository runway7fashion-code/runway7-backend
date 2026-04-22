<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sender->first_name }} from Runway 7</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background:#f0f0f0; color:#1a1a1a; }
        .wrap { padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background:#000; padding: 20px 40px; text-align:center; }
        .header img { max-width:160px; height:auto; display:block; margin:0 auto; }
        .body { padding: 32px 40px; font-size:15px; line-height:1.7; color:#333; white-space: pre-wrap; }
        .signature { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; font-size: 13px; color: #666; }
        .footer { background:#000; padding:20px 40px; text-align:center; color:#888; font-size:12px; }
        .footer a { color:#D4AF37; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="header">
            <img src="{{ asset('images/logo.webp') }}" alt="Runway 7">
        </div>
        <div class="body">{!! nl2br(e($bodyText)) !!}<div class="signature">
            — {{ $sender->first_name }} {{ $sender->last_name }}<br>
            Runway 7 Partnerships<br>
            {{ $sender->email }}
        </div></div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Runway 7 Fashion. <a href="https://runway7fashion.com">runway7fashion.com</a></p>
        </div>
    </div>
</div>
</body>
</html>
