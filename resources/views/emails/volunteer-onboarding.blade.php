<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to Runway 7 — Volunteer Onboarding</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; display: block; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        td { vertical-align: top; }
        a { text-decoration: none; }

        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card   { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }

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

    <!-- Header with logo -->
    <div class="header">
        <img src="{{ url('/images/logo-email.png') }}"
             alt="Runway 7"
             width="100"
             height="100" />
    </div>

    <!-- Body -->
    <div class="body">
        <p class="greeting">Welcome, {{ $volunteer->first_name }}!</p>

        <p class="text">
            Congratulations! You have been selected as a volunteer for <strong>Runway 7 Fashion</strong>{{ $eventName ? ' at ' . $eventName : '' }}.
            We're excited to have you on our team! Below you'll find everything you need to get started.
        </p>

        @if($eventName || count($schedules) > 0)
        <div class="info-box">
            @if($eventName)
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">Volunteer</span>
            </div>
            @foreach($schedules as $schedule)
            <div class="info-row">
                <span class="info-label">{{ $schedule['day'] }}</span>
                <span class="info-value">
                    {{ \Carbon\Carbon::parse($schedule['start'])->format('g:i A') }} — {{ \Carbon\Carbon::parse($schedule['end'])->format('g:i A') }}
                    @if($schedule['area'])
                        <span style="color:#D4AF37; font-size:12px;"> ({{ $schedule['area'] }})</span>
                    @endif
                </span>
            </div>
            @endforeach
        </div>
        @endif

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
                    <strong style="color:#111111;">Check your schedule</strong> — your assigned days, times, and area will appear in the app.
                </td>
            </tr>
            <tr>
                <td style="width:24px; vertical-align:top; padding:12px 0;">
                    <div style="width:8px; height:8px; background-color:#D4AF37; border-radius:50%; margin-top:6px;"></div>
                </td>
                <td style="vertical-align:top; padding:12px 0; font-size:14px; line-height:1.6; color:#444444;">
                    <strong style="color:#111111;">Show your QR pass</strong> at the venue entrance for check-in each day.
                </td>
            </tr>
        </table>

        <p class="text">
            Use the following credentials to log in:
        </p>

        <table class="credentials-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Email</td>
                <td style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">{{ $volunteer->email }}</td>
            </tr>
            <tr>
                <td style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Password</td>
                <td style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">runway7</td>
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
            If you have any questions, feel free to contact us at
            <a href="mailto:operations@runway7fashion.com" style="color:#D4AF37; font-weight:600;">operations@runway7fashion.com</a>
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-brand">Runway 7</div>
        <div class="footer-text">
            This email was sent to {{ $volunteer->email }}<br>
            &copy; {{ date('Y') }} Runway 7 Fashion. All rights reserved.
        </div>
    </div>

</div>
</div>
</body>
</html>
