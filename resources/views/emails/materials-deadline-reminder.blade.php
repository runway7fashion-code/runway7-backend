<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Materials Deadline Reminder</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100% !important; height: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f0f0f0; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; display: block; outline: none; text-decoration: none; }
        table { border-collapse: collapse; }
        td { vertical-align: top; }
        a { text-decoration: none; }

        .email-wrapper { width: 100%; background-color: #f0f0f0; padding: 32px 16px; }
        .email-card   { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }

        .header { background-color: #000000; padding: 28px 40px; text-align: center; }
        .header img { width: 100px; height: 100px; margin: 0 auto; object-fit: contain; }

        .accent-bar { height: 4px; background: linear-gradient(90deg, #D4AF37 0%, #f0d060 50%, #D4AF37 100%); }

        .body { padding: 36px 40px; }

        .greeting { font-size: 22px; font-weight: 700; color: #000000; margin-bottom: 14px; line-height: 1.3; }
        .text { font-size: 15px; line-height: 1.75; color: #555555; margin-bottom: 24px; }

        .badge { display: inline-block; color: #D4AF37; font-size: 10px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 20px; background-color: #000000; }
        .badge-overdue { background-color: #b91c1c; color: #ffffff; }

        .info-box { background-color: #f7f7f7; border-left: 3px solid #D4AF37; border-radius: 0 10px 10px 0; padding: 20px 24px; margin: 0 0 28px 0; }
        .info-box-overdue { border-left-color: #b91c1c; background-color: #fef2f2; }
        .info-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #ebebeb; }
        .info-row:first-child { padding-top: 0; }
        .info-row:last-child  { border-bottom: none; padding-bottom: 0; }
        .info-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #999999; white-space: nowrap; min-width: 100px; padding-top: 2px; }
        .info-value { font-size: 15px; font-weight: 700; color: #111111; line-height: 1.4; }
        .info-value-red { color: #b91c1c; }

        .cta { display: inline-block; background-color: #000000; color: #ffffff; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; padding: 14px 32px; border-radius: 100px; margin: 4px 0 24px; }

        .divider { height: 1px; background-color: #f0f0f0; margin: 28px 0; }

        .footer { background-color: #f7f7f7; border-top: 1px solid #e8e8e8; padding: 24px 40px; text-align: center; }
        .footer-brand { font-size: 12px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; color: #000000; margin-bottom: 8px; }
        .footer-text { font-size: 11px; color: #aaaaaa; line-height: 1.6; }

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
        <img src="{{ url('/images/logo-email.png') }}" alt="Runway 7" width="100" height="100" />
    </div>

    <div class="accent-bar"></div>

    <div class="body">

        @php
            $isOverdue = $stage === 'overdue';
            $isUrgent  = in_array($stage, ['today', 'tomorrow', 'soon']);

            $titles = [
                'early'    => "Save the date, {$designer->first_name}",
                'upcoming' => "One week to go, {$designer->first_name}",
                'soon'     => "Only 3 days left, {$designer->first_name}",
                'tomorrow' => "Your deadline is tomorrow, {$designer->first_name}",
                'today'    => "Today is the day, {$designer->first_name}",
                'overdue'  => "Your deadline has passed, {$designer->first_name}",
            ];

            $intros = [
                'early'    => "This is an early reminder that the deadline to upload your materials for <strong>{$eventName}</strong> is approaching. You have 30 days left — plenty of time to gather everything you need.",
                'upcoming' => "One week remains to upload all your materials for <strong>{$eventName}</strong>. Make sure everything is in order to keep your participation on track.",
                'soon'     => "The deadline for <strong>{$eventName}</strong> is just 3 days away. Please finalize and upload your remaining materials to avoid any inconvenience.",
                'tomorrow' => "Tomorrow is the final day to submit your materials for <strong>{$eventName}</strong>. Please complete your uploads today or early tomorrow.",
                'today'    => "Today is the last day to upload your materials for <strong>{$eventName}</strong>. After today, uploads will be blocked.",
                'overdue'  => "The deadline to upload your materials for <strong>{$eventName}</strong> has passed. Uploads are now blocked. Please contact your advisor to request an extension.",
            ];

            $badgeText = [
                'early'    => 'Friendly reminder',
                'upcoming' => '7 days to go',
                'soon'     => '3 days left',
                'tomorrow' => 'Tomorrow',
                'today'    => 'Today',
                'overdue'  => 'Deadline passed',
            ];
        @endphp

        <div class="badge {{ $isOverdue ? 'badge-overdue' : '' }}">{{ $badgeText[$stage] ?? 'Reminder' }}</div>

        <p class="greeting">{{ $titles[$stage] ?? "Materials reminder, {$designer->first_name}" }}</p>

        <p class="text">{!! $intros[$stage] ?? '' !!}</p>

        <div class="info-box {{ $isOverdue ? 'info-box-overdue' : '' }}">
            <div class="info-row">
                <span class="info-label">Event</span>
                <span class="info-value">{{ $eventName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Deadline</span>
                <span class="info-value {{ $isOverdue || $isUrgent ? 'info-value-red' : '' }}">{{ $deadlineDate }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Pending</span>
                <span class="info-value {{ $pendingCount > 0 ? 'info-value-red' : '' }}">
                    {{ $pendingCount }} {{ $pendingCount === 1 ? 'material' : 'materials' }}
                </span>
            </div>
        </div>

        @if(!$isOverdue)
            <p class="text" style="margin-bottom:12px;">Open the Runway 7 app to finish uploading:</p>
            <p style="text-align:center; margin-bottom:28px;">
                <a href="{{ url('/') }}" class="cta">Open Runway 7 app</a>
            </p>
        @else
            <p class="text">
                If you believe this is an error or you need more time, reply to this email or reach your assigned advisor directly.
            </p>
        @endif

        <div class="divider"></div>

        <p class="text" style="font-size:13px; color:#999999; margin-bottom:0;">
            Questions? Reach us at
            <a href="mailto:operations@runway7fashion.com" style="color:#D4AF37; font-weight:600;">operations@runway7fashion.com</a>
        </p>

    </div>

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
