<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Runway 7 — Casting Details</title>
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
        .header { background-color: #000000; padding: 36px 40px; text-align: center; }
        .header img { width: 120px; height: 120px; margin: 0 auto; object-fit: contain; }

        /* Body */
        .body { padding: 36px 40px; }
        .greeting { font-size: 22px; font-weight: 700; color: #000000; margin-bottom: 14px; line-height: 1.3; }
        .text { font-size: 15px; line-height: 1.75; color: #555555; margin-bottom: 24px; }

        /* Tag badge */
        .tag-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
        }
        .tag-merch { background-color: #FFF7ED; color: #C2410C; border: 1px solid #FDBA74; }
        .tag-brand { background-color: #F0FDF4; color: #166534; border: 1px solid #86EFAC; }

        /* Info box */
        .info-box { background-color: #f7f7f7; border-left: 3px solid #D4AF37; border-radius: 0 10px 10px 0; padding: 20px 24px; margin: 0 0 28px 0; }
        .info-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #ebebeb; }
        .info-row:first-child { padding-top: 0; }
        .info-row:last-child  { border-bottom: none; padding-bottom: 0; }
        .info-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #999999; white-space: nowrap; min-width: 100px; padding-top: 2px; }
        .info-value { font-size: 15px; font-weight: 700; color: #111111; line-height: 1.4; }

        /* Credentials table */
        .credentials-table { width: 100%; border-collapse: collapse; margin: 0 0 28px 0; border-radius: 12px; overflow: hidden; }
        .credentials-table td { padding: 14px 20px; font-size: 15px; }
        .credentials-label { background-color: #000000; color: rgba(255,255,255,0.6); font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; width: 110px; }
        .credentials-value { background-color: #f7f7f7; font-weight: 700; color: #111111; }

        /* Footer */
        .footer { background-color: #f7f7f7; border-top: 1px solid #e8e8e8; padding: 24px 40px; text-align: center; }
        .footer-brand { font-size: 12px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: #000000; margin-bottom: 8px; }
        .footer-text { font-size: 11px; color: #aaaaaa; line-height: 1.6; }

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

        @if($tag === 'runway_merch')
        <p class="text">
            Congratulations! You have been selected to attend the <strong>Runway 7 Merch Model Casting</strong>.
            While you have not been chosen for the designer casting, we are pleased to formally invite you
            to participate in our exclusive merch casting.
        </p>
        <p class="text">
            Selected models will have the opportunity to take part in our <strong>live merch runway commercial</strong>
            during Fashion Week and be featured across our official social media platforms.
            We look forward to seeing you there!
        </p>
        <span class="tag-badge tag-merch">Runway 7 Merch Casting</span>
        @elseif($tag === 'runway_brand')
        <p class="text">
            Great news! Your casting schedule is confirmed.
            You will be walking for the <strong>designers</strong> at Runway 7 Fashion.
            Below you will find your casting details and access information.
        </p>
        <p class="text">
            As part of your official merch purchase, you'll receive <strong>exclusive skip-the-line access</strong> on casting day.
        </p>
        <span class="tag-badge tag-brand">Designer Runway</span>
        @else
        <p class="text">
            Great news! Your casting schedule is confirmed.
            Welcome to the <strong>Runway 7 Fashion</strong> team.
            Below you will find your casting details and access information.
        </p>
        @endif

        @foreach($events as $event)
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $event['name'] }}</span>
            </div>
            @if($event['casting_date'])
            <div class="info-row">
                <span class="info-label">Casting Date</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($event['casting_date'])->format('l, F j, Y') }}</span>
            </div>
            @endif
            @if($event['casting_time'])
            <div class="info-row">
                <span class="info-label">Casting Time</span>
                <span class="info-value">{{ $event['casting_time'] }}</span>
            </div>
            @endif
        </div>
        @endforeach

        <p class="text">
            Use the following credentials to log in to the Runway 7 app:
        </p>

        <table class="credentials-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="credentials-label" style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Email</td>
                <td class="credentials-value" style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">{{ $model->email }}</td>
            </tr>
            <tr>
                <td class="credentials-label" style="background-color:#000000; color:rgba(255,255,255,0.6); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; width:110px; padding:14px 20px;">Password</td>
                <td class="credentials-value" style="background-color:#f7f7f7; font-weight:700; color:#111111; padding:14px 20px;">runway7</td>
            </tr>
        </table>

        <p class="text">Download the app and complete your <strong>Comp Card</strong> with your photos before the casting day. It's quick and easy!</p>

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

        @if(!$tag)
        <p class="text" style="font-size: 14px; background-color: #FFF7ED; border-left: 3px solid #D4AF37; border-radius: 0 8px 8px 0; padding: 16px 20px;">
            <strong>Want to skip the line?</strong> By making a minimum purchase of $100 from our official merch,
            you will get priority access and be seen faster by top designers.
            <a href="https://runway7.co/Skiptheline" style="color:#D4AF37; font-weight:700;">Shop now and secure your fast pass here.</a>
        </p>
        @endif

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            If you have any questions, you can reply to this email or contact us at
            <a href="mailto:models@runway7fashion.com" style="color:#D4AF37; font-weight:600;">models@runway7fashion.com</a>
        </p>
    </div>

    <!-- Footer -->
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
