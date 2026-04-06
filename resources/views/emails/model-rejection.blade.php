<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Casting Update — Runway 7</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; display: block; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        td { vertical-align: top; }
        a { text-decoration: none; }
        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
        .header { background-color: #000000; padding: 36px 40px; text-align: center; }
        .header img { width: 120px; height: 120px; margin: 0 auto; object-fit: contain; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 22px; font-weight: 700; color: #000000; margin-bottom: 14px; line-height: 1.3; }
        .text { font-size: 15px; line-height: 1.75; color: #555555; margin-bottom: 24px; }
        .highlight-box { background-color: #FFF7ED; border-left: 3px solid #D4AF37; border-radius: 0 10px 10px 0; padding: 20px 24px; margin: 0 0 28px 0; }
        .highlight-title { font-size: 16px; font-weight: 700; color: #000000; margin-bottom: 8px; }
        .highlight-text { font-size: 14px; line-height: 1.7; color: #555555; }
        .cta-btn { display: block; text-align: center; background-color: #D4AF37; color: #ffffff; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 14px 28px; border-radius: 8px; margin: 0 0 28px 0; }
        .cta-btn:hover { background-color: #222222; }
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
            .highlight-box { padding: 16px 18px !important; }
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
        <p class="greeting">Hi, {{ $model->first_name }} 👋</p>

        <p class="text">
            Thank you for participating in our model casting process.
            After careful consideration, we regret to inform you that you have not been selected
            to participate in the main model casting at this time.
        </p>

        <div class="highlight-box">
            <p class="highlight-title">But wait — your chance doesn't end here! ✨</p>
            <p class="highlight-text">
                Still interested in walking for Runway 7? Join our <strong>Merch Casting</strong>.
                By making a minimum purchase of <strong>$100</strong> from our official merch,
                you will automatically be entered into our exclusive merch casting, taking place
                on <strong>September 7th at 5–7 PM</strong>.
            </p>
            <p class="highlight-text" style="margin-top: 12px;">
                Selected models will have the opportunity to take part in our
                <strong>live merch runway commercial</strong> during Fashion Week and be featured
                across our official social media platforms.
            </p>
        </div>

        <a href="https://runway7.co/modelcasting" class="cta-btn" style="color: #ffffff;">
            Shop Now & Join Merch Casting
        </a>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, feel free to contact us at
            <a href="mailto:models@runway7fashion.com" style="color:#D4AF37; font-weight:600;">models@runway7fashion.com</a>
        </p>
    </div>

    <div class="footer">
        <div class="footer-brand">Runway 7</div>
        <div class="footer-text">
            This email was sent to {{ $model->email }}<br>
            &copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
