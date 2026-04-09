<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $emailSubject ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; }
        a { color: #D4AF37; text-decoration: none; }
        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background-color: #000000; padding: 28px 40px; text-align: center; }
        .header p { color: #888888; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
        .body-content { padding: 36px 40px; }
        .body-content p { font-size: 15px; line-height: 1.7; color: #444444; margin-bottom: 16px; }
        .signature { margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; }
        .signature p { font-size: 13px; color: #888; line-height: 1.5; margin-bottom: 4px; }
        .signature .name { color: #1a1a1a; font-weight: 600; font-size: 14px; }
        .footer { background-color: #000000; padding: 24px 40px; text-align: center; }
        .footer p { color: #888888; font-size: 12px; line-height: 1.6; }
        .footer a { color: #D4AF37; }
        @media only screen and (max-width: 600px) {
            .body-content { padding: 24px 20px; }
            .header { padding: 20px; }
            .footer { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-card">
        <!-- Header -->
        <div class="header">
            <img src="{{ asset('images/logo.webp') }}" alt="Runway 7" style="max-width: 180px; height: auto; margin: 0 auto 8px auto; display: block;">
            <p>Fashion Week</p>
        </div>

        <!-- Body -->
        <div class="body-content">
            {!! $body !!}

            <div class="signature">
                <p class="name">{{ $senderName }}</p>
                <p>Runway 7 Fashion</p>
                <p><a href="mailto:{{ $senderEmail }}">{{ $senderEmail }}</a></p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.</p>
            <p><a href="https://runway7fashion.com">runway7fashion.com</a></p>
        </div>
    </div>
</div>
</body>
</html>
