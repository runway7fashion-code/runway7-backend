<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to Runway 7 — Media Assistant Access</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; display: block; outline: none; text-decoration: none; }
        table { border-collapse: collapse; }
        td { vertical-align: top; }
        a { text-decoration: none; }
        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background-color: #000000; padding: 36px 40px; text-align: center; }
        .header img { width: 120px; height: 120px; margin: 0 auto; object-fit: contain; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 22px; font-weight: 700; color: #000000; margin-bottom: 14px; line-height: 1.3; }
        .text { font-size: 15px; line-height: 1.75; color: #555555; margin-bottom: 24px; }
        .info-box { background-color: #f7f7f7; border-left: 3px solid #D4AF37; border-radius: 0 10px 10px 0; padding: 20px 24px; margin: 0 0 28px 0; }
        .info-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #ebebeb; }
        .info-row:first-child { padding-top: 0; }
        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #999999; white-space: nowrap; min-width: 100px; padding-top: 2px; }
        .info-value { font-size: 15px; font-weight: 700; color: #111111; line-height: 1.4; }
        .credentials-table { width: 100%; border-collapse: collapse; margin: 0 0 28px 0; border-radius: 12px; overflow: hidden; }
        .credentials-table td { padding: 14px 20px; font-size: 15px; }
        .footer { background-color: #f7f7f7; border-top: 1px solid #e8e8e8; padding: 24px 40px; text-align: center; }
        .footer-brand { font-size: 12px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: #000000; margin-bottom: 8px; }
        .footer-text { font-size: 11px; color: #aaaaaa; line-height: 1.6; }
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 16px 12px !important; }
            .header { padding: 28px 24px !important; }
            .header img { width: 90px !important; height: 90px !important; }
            .body { padding: 28px 24px !important; }
            .greeting { font-size: 20px !important; }
            .text { font-size: 14px !important; }
            .info-box { padding: 16px 18px !important; }
            .info-row { flex-direction: column !important; gap: 4px !important; }
            .footer { padding: 20px 24px !important; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="email-card">

    <div class="header">
        <img src="{{ url('/images/logo-email.png') }}" alt="Runway 7" width="120" height="120" />
    </div>

    <div class="body">
        <p class="greeting">Hi, {{ $assistant->first_name }}! 👋</p>

        <p class="text">
            You have been registered as a <strong>media assistant</strong> for <strong>Runway 7 Fashion</strong>.
            Below you will find your access credentials and event details.
        </p>

        <div class="info-box">
            @if($mediaName)
            <div class="info-row">
                <span class="info-label">Assigned To</span>
                <span class="info-value">{{ $mediaName }}</span>
            </div>
            @endif
            @if($eventName)
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">Media Assistant</span>
            </div>
        </div>

        <p class="text">
            Use the following credentials to log in to the Runway 7 app:
        </p>

        <table class="credentials-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Email</td>
                <td style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">{{ $assistant->email }}</td>
            </tr>
            <tr>
                <td style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Password</td>
                <td style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">runway7</td>
            </tr>
        </table>

        <p class="text">Download the app to access your media pass and event schedule.</p>

        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 28px auto;">
            <tr>
                <td style="padding-right:16px;">
                    <a href="{{ config('services.app_stores.apple') }}">
                        <img src="{{ url('/images/app-store.png') }}" alt="Download on the App Store" height="44" style="display:block;" />
                    </a>
                </td>
                <td>
                    <a href="{{ config('services.app_stores.google') }}">
                        <img src="{{ url('/images/google-play.png') }}" alt="Get it on Google Play" height="44" style="display:block;" />
                    </a>
                </td>
            </tr>
        </table>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, you can reply to this email or contact us at
            <a href="mailto:operations@runway7fashion.com" style="color:#D4AF37; font-weight:600;">operations@runway7fashion.com</a>
        </p>
    </div>

    <div class="footer">
        <div class="footer-brand">Runway 7</div>
        <div class="footer-text">
            This email was sent to {{ $assistant->email }}<br>
            &copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
