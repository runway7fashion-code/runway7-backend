<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Thank you for your interest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; }
        a { text-decoration: none; }
        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background-color: #000000; padding: 28px 40px; text-align: center; }
        .header p { color: #888888; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
        .body-content { padding: 36px 40px; }
        .body-content h2 { font-size: 20px; font-weight: 700; color: #000000; margin-bottom: 16px; }
        .body-content p { font-size: 15px; line-height: 1.7; color: #444444; margin-bottom: 16px; }
        .highlight-box { background-color: #000; border-radius: 12px; padding: 20px 24px; margin: 24px 0; text-align: center; }
        .highlight-box p { color: #ffffff; font-size: 14px; line-height: 1.6; margin: 0; }
        .highlight-box .gold { color: #D4AF37; font-weight: 700; }
        .notice { background-color: #fff8e1; border-left: 4px solid #D4AF37; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #5c4a10; line-height: 1.6; }
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
        <div class="header">
            <img src="{{ asset('images/logo.webp') }}" alt="Runway 7" style="max-width: 180px; height: auto; margin: 0 auto 8px auto; display: block;">
            <p>Partnerships</p>
        </div>

        <div class="body-content">
            <h2>Thank you, {{ $lead->first_name }}!</h2>

            <p>We've received your partnership inquiry for <strong>{{ $lead->company?->name }}</strong>. Our Sponsorship team is reviewing your submission and will be in touch shortly.</p>

            <div class="highlight-box">
                <p>Our team will contact you within the next <span class="gold">24–48 hours</span> to discuss partnership opportunities at our upcoming events.</p>
            </div>

            <p style="font-size: 14px; color: #444;">In the meantime, if you'd like to share anything else about your brand or goals, you can reply directly to this email.</p>

            <div class="notice">
                <strong>Security notice:</strong> Runway 7 is dedicated to providing a secure environment for all partners. We never request payments or sensitive financial information through our forms. If something feels off, please contact us immediately at
                <a href="mailto:sponsorsrelations@runway7fashion.com" style="color:#5c4a10; text-decoration:underline;">sponsorsrelations@runway7fashion.com</a>.
            </div>

            <p style="font-size: 13px; color: #888; margin-top: 20px;">For any questions, reach out to us at
                <a href="mailto:partnerships@runway7fashion.com" style="color: #D4AF37;">partnerships@runway7fashion.com</a>
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
