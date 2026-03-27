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
        .header h1 { color: #D4AF37; font-size: 28px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; margin: 0; }
        .header p { color: #888888; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
        .body-content { padding: 36px 40px; }
        .body-content h2 { font-size: 20px; font-weight: 700; color: #000000; margin-bottom: 16px; }
        .body-content p { font-size: 15px; line-height: 1.7; color: #444444; margin-bottom: 16px; }
        .info-box { background-color: #f8f8f8; border-radius: 12px; padding: 20px 24px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 13px; color: #888; font-weight: 500; }
        .info-value { font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right; }
        .highlight-box { background-color: #000; border-radius: 12px; padding: 20px 24px; margin: 24px 0; text-align: center; }
        .highlight-box p { color: #ffffff; font-size: 14px; line-height: 1.6; margin: 0; }
        .highlight-box .gold { color: #D4AF37; font-weight: 700; }
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
            <h2>Thank you, {{ $lead->first_name }}!</h2>

            <p>We have received your application to showcase at Runway 7 Fashion Week. Our Designer Relations team is reviewing your submission.</p>

            <div class="info-box">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Name</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->first_name }} {{ $lead->last_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Email</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->email }}</td>
                    </tr>
                    @if($lead->phone)
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Phone</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->phone }}</td>
                    </tr>
                    @endif
                    @if($lead->country)
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Country</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->country }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Company</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->company_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #888;">Category</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->retail_category }}</td>
                    </tr>
                    @if($lead->event)
                    <tr>
                        <td style="padding: 8px 0; font-size: 13px; color: #888;">Event</td>
                        <td style="padding: 8px 0; font-size: 13px; color: #1a1a1a; font-weight: 600; text-align: right;">{{ $lead->event->name }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div class="highlight-box">
                <p>Our team will contact you within the next <span class="gold">24–48 hours</span> to discuss your participation and available packages.</p>
            </div>

            <p style="font-size: 13px; color: #888; margin-top: 20px;">If you have any questions in the meantime, please don't hesitate to reach out to us at <a href="mailto:designers@runway7fashion.com" style="color: #D4AF37;">designers@runway7fashion.com</a></p>
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
