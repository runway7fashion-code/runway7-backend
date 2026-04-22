<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Runway 7</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; }
        a { text-decoration: none; }
        .wrap { padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background-color: #000; padding: 28px 40px; text-align: center; }
        .header p { color: #888; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-top: 6px; }
        .body { padding: 36px 40px; }
        .body h2 { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
        .body p { font-size: 15px; line-height: 1.7; color: #444; margin-bottom: 14px; }
        .creds { background:#000; border-radius:12px; padding:22px 24px; margin:24px 0; color:#fff; }
        .creds .row { display:flex; justify-content:space-between; padding: 6px 0; border-bottom:1px solid #333; }
        .creds .row:last-child { border:none; }
        .creds .label { font-size: 12px; color: #888; }
        .creds .value { font-size: 14px; color: #D4AF37; font-weight: 600; font-family: monospace; }
        .apps { text-align:center; margin: 28px 0 10px; }
        .apps a { display:inline-block; margin: 6px 8px; background:#000; color:#fff; padding: 12px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; }
        .footer { background:#000; padding: 24px 40px; text-align:center; }
        .footer p { color: #888; font-size: 12px; line-height: 1.6; }
        .footer a { color: #D4AF37; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="header">
            <img src="{{ asset('images/logo.webp') }}" alt="Runway 7" style="max-width: 180px; height: auto; display: block; margin: 0 auto;">
            <p>Partnerships</p>
        </div>
        <div class="body">
            <h2>Welcome on board, {{ $sponsor->first_name }}!</h2>
            <p>Your Runway 7 sponsor account has been activated. Use the credentials below to log in to the Runway 7 mobile app and manage your partnership.</p>

            <div class="creds">
                <div class="row">
                    <span class="label">Email</span>
                    <span class="value">{{ $sponsor->email }}</span>
                </div>
                <div class="row">
                    <span class="label">Password</span>
                    <span class="value">{{ $password }}</span>
                </div>
            </div>

            <p>From the app you can:</p>
            <ul style="margin-left:20px; color:#444; font-size:15px; line-height:1.7;">
                <li>View your event pass and schedule</li>
                <li>Create tickets for your invited guests</li>
                <li>Access event info and announcements</li>
            </ul>

            <div class="apps">
                <a href="https://play.google.com/store/apps/details?id=com.runway7fashion">Google Play</a>
                <a href="https://apps.apple.com/app/runway7fashion">App Store</a>
            </div>

            <p style="font-size: 13px; color: #888; margin-top: 20px;">If you need help, contact us at
                <a href="mailto:sponsorsrelations@runway7fashion.com" style="color:#D4AF37;">sponsorsrelations@runway7fashion.com</a>.
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.</p>
            <p><a href="https://runway7fashion.com">runway7fashion.com</a></p>
        </div>
    </div>
</div>
</body>
</html>
