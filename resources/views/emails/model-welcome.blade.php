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

        /* Code box */
        .code-box {
            background-color: #000000;
            border-radius: 12px;
            padding: 24px 20px;
            text-align: center;
            margin: 0 0 28px 0;
        }
        .code-label {
            color: rgba(255,255,255,0.5);
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .code-value {
            color: #D4AF37;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 8px;
            font-family: 'Courier New', Courier, monospace;
        }

        /* App store badges */
        .stores-wrapper {
            text-align: center;
            margin: 0 0 28px 0;
        }
        .stores-inner {
            display: inline-flex;
            gap: 16px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }
        .store-badge img {
            height: 44px;
            width: auto;
            display: block;
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

            .header { padding: 28px 24px !important; }
            .header img { width: 90px !important; height: 90px !important; }

            .body { padding: 28px 24px !important; }

            .greeting { font-size: 20px !important; }
            .text { font-size: 14px !important; }

            .info-box { padding: 16px 18px !important; }
            .info-row { flex-direction: column !important; gap: 4px !important; }
            .info-label { min-width: unset !important; }
            .info-value { font-size: 14px !important; }

            .code-value { font-size: 26px !important; letter-spacing: 5px !important; }

            .stores-inner { gap: 12px !important; }
            .store-badge img { height: 38px !important; }

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
            Welcome to the <strong>Runway7 Fashion</strong> team.
            We are excited to have you as part of this event.
            Below you will find your access information and the details of the Model Casting.
        </p>

        @if($eventName || $castingDate || $castingTime)
        <div class="info-box">
            @if($eventName)
            <div class="info-row">
                <span class="info-label margin-right-10">Event:</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            @endif
            @if($castingDate)
            <div class="info-row">
                <span class="info-label margin-right-10">Casting Date:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($castingDate)->format('l, F j, Y') }}</span>
            </div>
            @endif
            @if($castingTime)
            <div class="info-row">
                <span class="info-label margin-right-10">Casting Time:</span>
                <span class="info-value">{{ $castingTime }}</span>
            </div>
            @endif
        </div>
        @endif

        <p class="text">
            Use the following code to access the Runway 7 app and complete your profile before the casting day:
        </p>

        <div class="code-box">
            <div class="code-label">Your access code</div>
            <div class="code-value">{{ $model->login_code }}</div>
        </div>

        <p class="text" style="margin-top:16px;">Download the app, enter this code and complete your <strong>Comp Card</strong> with your photos before the casting day, so designers can view your profile. It's quick and easy!</p>

        <!-- App Store Badges -->
        <div class="stores-wrapper">
            <div class="stores-inner">
                <a href="#" class="store-badge">
                    <img src="{{ url('/images/app-store.png') }}"
                         alt="Download on the App Store"
                         height="44" />
                </a>
                <a href="#" class="store-badge">
                    <img src="{{ url('/images/google-play.png') }}"
                         alt="Get it on Google Play"
                         height="44" />
                </a>
            </div>
        </div>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, you can reply to this email or contact us at
            <a href="mailto:tickets@runway7fashion.com" style="color:#D4AF37; font-weight:600;">tickets@runway7fashion.com</a>
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-brand">Runway 7</div>
        <div class="footer-text">
            This email was sent to {{ $model->email }}<br>
            © {{ date('Y') }} Runway 7 Fashion Week. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
