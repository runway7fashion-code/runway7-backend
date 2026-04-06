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
        .hero img { width: 100%; height: auto; display: block; }

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

        /* Gold accent bar */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, #D4AF37 0%, #f0d060 50%, #D4AF37 100%);
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

        /* Highlight badge */
        .badge {
            display: inline-block;
            background-color: #000000;
            color: #D4AF37;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 100px;
            margin-bottom: 20px;
        }

        /* Steps */
        .steps { margin: 0 0 28px 0; }
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

        /* Divider */
        .divider {
            height: 1px;
            background-color: #f0f0f0;
            margin: 28px 0;
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

    <!-- Gold accent bar -->
    <div class="accent-bar"></div>

    <!-- Body -->
    <div class="body">

        <div class="badge">You're In ✦</div>

        <p class="greeting">Welcome to Runway 7, {{ $designer->first_name }}!</p>

        <p class="text">
            We're excited to confirm that you've been successfully registered as a designer with
            <strong>Runway 7 Fashion</strong>. Your journey on the runway starts here.
        </p>

        <!-- Registration summary -->
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Designer</span>
                <span class="info-value">{{ $designer->first_name }} {{ $designer->last_name }}</span>
            </div>
            @if($brandName)
            <div class="info-row">
                <span class="info-label">Brand</span>
                <span class="info-value">{{ $brandName }}</span>
            </div>
            @endif
            @if($eventName)
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value" style="color:#D4AF37;">Registered ✓</span>
            </div>
        </div>

        <p class="text">
            Our team is already working on your onboarding. Here's what happens next:
        </p>

        <!-- What's next steps -->
        <table cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 28px 0;">
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:14px; line-height:1.6; color:#444444;">
                    Our <strong style="color:#111111;">operations team</strong> will reach out shortly with your full onboarding details, including app access and show scheduling.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:14px; line-height:1.6; color:#444444;">
                    You'll receive a <strong style="color:#111111;">separate email with your login credentials</strong> to access the Runway 7 designer app.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; font-size:14px; line-height:1.6; color:#444444;">
                    Through the app you'll be able to <strong style="color:#111111;">upload your materials</strong> — logo, collection photos, music, and runway video.
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            Questions? Contact your sales representative or reach us at
            <a href="mailto:designers@runway7fashion.com" style="color:#D4AF37; font-weight:600;">designers@runway7fashion.com</a>
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
