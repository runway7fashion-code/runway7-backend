<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Thank You for Applying — Runway 7</title>
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

        /* Header */
        .header {
            background-color: #000000;
            padding: 36px 40px;
            text-align: center;
        }
        .header img {
            width: 120px;
            height: 120px;
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

        /* Steps */
        .steps-box {
            background-color: #fafafa;
            border-radius: 12px;
            padding: 24px;
            margin: 0 0 28px 0;
        }
        .steps-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #999999;
            margin-bottom: 16px;
        }
        .step {
            font-size: 14px;
            color: #555555;
            line-height: 1.6;
            margin-bottom: 12px;
            padding-left: 8px;
        }
        .step:last-child { margin-bottom: 0; }

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

            .header { padding: 28px 24px !important; }
            .header img { width: 90px !important; height: 90px !important; }

            .body { padding: 28px 24px !important; }

            .greeting { font-size: 20px !important; }
            .text { font-size: 14px !important; }

            .info-box { padding: 16px 18px !important; }
            .info-row { flex-direction: column !important; gap: 4px !important; }
            .info-label { min-width: unset !important; }
            .info-value { font-size: 14px !important; }

            .steps-box { padding: 18px !important; }
            .step-text { font-size: 13px !important; }

            .footer { padding: 20px 24px !important; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="email-card">

    <!-- Header with logo -->
    <div class="header">
        <img src="{{ url('/images/logo-email.png') }}"
             alt="Runway 7"
             width="120"
             height="120" />
    </div>

    <!-- Body -->
    <div class="body">
        <p class="greeting">Hi, {{ $model->first_name }}! 👋</p>

        <p class="text">
            Thank you for applying to <strong>Runway7 Fashion</strong>!
            We have successfully received your application and our team is currently reviewing your profile.
        </p>

        @if($eventName)
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Event Applied:</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value" style="color: #D4AF37;">Under Review</span>
            </div>
        </div>
        @endif

        <div class="steps-box">
            <div class="steps-title">What happens next?</div>
            <div class="step">&#8226; Our team will carefully review your application and photos.</div>
            <div class="step">&#8226; If you qualify, we will contact you with the details for the <strong>Model Casting</strong>.</div>
            <div class="step">&#8226; You will receive a welcome email with your access to the Runway7 app.</div>
        </div>

        <p class="text">
            Please note that due to the high volume of applications, the review process may take a few days.
            We appreciate your patience and interest in being part of Runway 7!
        </p>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, feel free to contact us at
            <a href="mailto:operation@runway7fashion.com" style="color:#D4AF37; font-weight:600;">operation@runway7fashion.com</a>
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-brand">Runway7 Fashion</div>
        <div class="footer-text">
            This email was sent to {{ $model->email }}<br>
            © {{ date('Y') }} Runway7 Fashion. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
