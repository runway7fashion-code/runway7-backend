<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to Runway 7</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; display: block; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        td { vertical-align: top; }
        a { text-decoration: none; }

        /* Layout */
        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card   { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }

        /* Hero image */
        .hero img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Header */
        .header {
            background-color: #000000;
            padding: 28px 40px;
            text-align: center;
        }
        .header img {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            object-fit: contain;
        }

        /* Body */
        .body { padding: 36px 40px; }

        .greeting {
            font-size: 22px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 14px;
            line-height: 1.3;
        }

        .text {
            font-size: 15px;
            line-height: 1.75;
            color: #555555;
            margin-bottom: 24px;
        }

        /* Info box */
        .info-box {
            background-color: #f7f7f7;
            border-left: 3px solid #D4AF37;
            border-radius: 0 10px 10px 0;
            padding: 20px 24px;
            margin: 0 0 28px 0;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #ebebeb;
        }
        .info-row:first-child { padding-top: 0; }
        .info-row:last-child  { border-bottom: none; padding-bottom: 0; }

        .info-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #999999;
            white-space: nowrap;
            min-width: 100px;
            padding-top: 2px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #111111;
            line-height: 1.4;
        }

        /* Credentials table */
        .credentials-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 28px 0;
            border-radius: 12px;
            overflow: hidden;
        }
        .credentials-table td {
            padding: 14px 20px;
            font-size: 15px;
        }
        .credentials-label {
            background-color: #000000;
            color: rgba(255,255,255,0.6);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            width: 110px;
        }
        .credentials-value {
            background-color: #f7f7f7;
            font-weight: 700;
            color: #111111;
        }

        /* Steps */
        .steps {
            margin: 0 0 28px 0;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .step:last-child { border-bottom: none; }
        .step-dot {
            width: 8px;
            height: 8px;
            background-color: #D4AF37;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 7px;
        }
        .step-text {
            font-size: 14px;
            line-height: 1.6;
            color: #444444;
        }
        .step-text strong { color: #111111; }

        /* App store badges */
        .stores-wrapper {
            text-align: center;
            margin: 0 0 28px 0;
        }
        .store-badge img {
            height: 44px;
            width: auto;
            display: inline-block;
        }

        /* Footer */
        .footer {
            background-color: #f7f7f7;
            border-top: 1px solid #e8e8e8;
            padding: 24px 40px;
            text-align: center;
        }
        .footer-brand {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #000000;
            margin-bottom: 8px;
        }
        .footer-text {
            font-size: 11px;
            color: #aaaaaa;
            line-height: 1.6;
        }

        /* ===== MOBILE ===== */
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 16px 12px !important; }
            .header { padding: 20px 24px !important; }
            .header img { width: 80px !important; height: 80px !important; }
            .body { padding: 28px 24px !important; }
            .greeting { font-size: 20px !important; }
            .text { font-size: 14px !important; }
            .info-box { padding: 16px 18px !important; }
            .info-row { flex-direction: column !important; gap: 4px !important; }
            .info-label { min-width: unset !important; }
            .info-value { font-size: 14px !important; }
            .store-badge img { height: 38px !important; }
            .footer { padding: 20px 24px !important; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="email-card">

    <!-- Hero image -->
    <div class="hero">
        <img src="{{ url('/images/designer-onboarding.webp') }}"
             alt="Runway 7 — New York Fashion Week"
             style="width:100%; height:auto; display:block;" />
    </div>

    <!-- Header with logo -->
    <div class="header">
        <img src="{{ url('/images/logo-email.png') }}"
             alt="Runway 7"
             width="100"
             height="100" />
    </div>

    <!-- Body -->
    <div class="body">
        <p class="greeting">Welcome, {{ $designer->first_name }}!</p>

        <p class="text">
            Thank you for joining <strong>Runway 7 Fashion</strong> as a designer.
            We're thrilled to have you showcase your collection{{ count($events) > 0 ? ' at ' . implode(' &amp; ', array_column($events, 'name')) : '' }}.
            Below you'll find everything you need to get started.
        </p>

        @foreach($events as $event)
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $event['name'] }}</span>
            </div>
            @foreach($event['shows'] as $show)
            <div class="info-row">
                <span class="info-label">{{ $show['day_label'] }}</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($show['day_date'])->format('l, F j') }} — {{ \Carbon\Carbon::parse($show['scheduled_time'])->format('g:i A') }}</span>
            </div>
            @endforeach
        </div>
        @endforeach

        <p class="text" style="margin-bottom: 16px;">
            <strong>Getting started is easy:</strong>
        </p>

        <table cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 28px 0;">
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:14px; line-height:1.6; color:#444444;">
                    <strong style="color:#111111;">Download the Runway 7 app</strong> from the App Store or Google Play.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:14px; line-height:1.6; color:#444444;">
                    <strong style="color:#111111;">Log in</strong> with the credentials below.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:14px; line-height:1.6; color:#444444;">
                    <strong style="color:#111111;">Upload your materials</strong> — logo, collection photos, music, and video for your runway presentation.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; font-size:14px; line-height:1.6; color:#444444;">
                    <strong style="color:#111111;">Select your models</strong> during casting day directly from the app.
                </td>
            </tr>
        </table>

        <p class="text">
            Use the following credentials to log in:
        </p>

        <table class="credentials-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="credentials-label" style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Email</td>
                <td class="credentials-value" style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">{{ $designer->email }}</td>
            </tr>
            <tr>
                <td class="credentials-label" style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Password</td>
                <td class="credentials-value" style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">runway7</td>
            </tr>
        </table>

        <!-- App Store Badges -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 28px auto;">
            <tr>
                <td style="padding-right:16px;">
                    <a href="{{ config('services.app_stores.apple') }}">
                        <img src="{{ url('/images/app-store.png') }}"
                             alt="Download on the App Store"
                             height="44" style="display:block;" />
                    </a>
                </td>
                <td>
                    <a href="{{ config('services.app_stores.google') }}">
                        <img src="{{ url('/images/google-play.png') }}"
                             alt="Get it on Google Play"
                             height="44" style="display:block;" />
                    </a>
                </td>
            </tr>
        </table>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, feel free to reply to this email or contact us at
            <a href="mailto:operations@runway7fashion.com" style="color:#D4AF37; font-weight:600;">operations@runway7fashion.com</a>
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-brand">Runway 7</div>
        <div class="footer-text">
            This email was sent to {{ $designer->email }}<br>
            &copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
