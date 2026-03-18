<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #ffffff;
    width: 100%;
    height: 100%;
    color: #000;
}

/* ── Borders ── */
.border-outer {
    position: absolute;
    top: 14px; left: 14px; right: 14px; bottom: 14px;
    border: 2px solid #000000;
}
.border-inner {
    position: absolute;
    top: 22px; left: 22px; right: 22px; bottom: 22px;
    border: 1px solid #D4AF37;
}

/* ── Corner accents ── */
.corner-h, .corner-v {
    position: absolute;
    background: #D4AF37;
}
/* Top-left */
.c-tl-h { top: 10px; left: 10px; width: 28px; height: 2.5px; }
.c-tl-v { top: 10px; left: 10px; width: 2.5px; height: 28px; }
/* Top-right */
.c-tr-h { top: 10px; right: 10px; width: 28px; height: 2.5px; }
.c-tr-v { top: 10px; right: 10px; width: 2.5px; height: 28px; }
/* Bottom-left */
.c-bl-h { bottom: 10px; left: 10px; width: 28px; height: 2.5px; }
.c-bl-v { bottom: 10px; left: 10px; width: 2.5px; height: 28px; }
/* Bottom-right */
.c-br-h { bottom: 10px; right: 10px; width: 28px; height: 2.5px; }
.c-br-v { bottom: 10px; right: 10px; width: 2.5px; height: 28px; }

/* ── Main content ── */
.main-content {
    position: relative;
    padding: 44px 80px 36px 80px;
    text-align: center;
}

/* ── Logo ── */
.logo-img {
    width: 200px;
    height: auto;
}

/* ── Title ── */
.cert-title {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 32px;
    font-weight: bold;
    color: #000000;
    letter-spacing: 1px;
    text-transform: uppercase;
    line-height: 1.15;
    margin: 22px 0 8px;
}

.cert-subtitle {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 18px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #D4AF37;
    margin-bottom: 16px;
}

/* ── Gold divider ── */
.gold-divider {
    width: 240px;
    height: 1px;
    background: #D4AF37;
    margin: 0 auto 20px auto;
}

/* ── Presented to ── */
.presented-label {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #616060;
    margin-bottom: 6px;
}

.recipient-name {
    font-family: Georgia, 'Times New Roman', Times, serif;
    font-style: italic;
    font-size: 42px;
    font-weight: 500;
    color: #000000;
    line-height: 1.15;
    margin-bottom: 5px;
}

.name-underline {
    width: 420px;
    height: 1px;
    background: #D4AF37;
    margin: 0 auto 18px auto;
}

/* ── Message ── */
.message-text {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12.5px;
    font-weight: 400;
    color: #555;
    line-height: 1.75;
    text-align: center;
    margin: 0 auto 18px auto;
    max-width: 750px;
}

/* ── Footer ── */
.footer-table {
    width: 100%;
    margin-top: 8px;
}
.footer-left {
    width: 33%;
    vertical-align: bottom;
    text-align: left;
}
.footer-center {
    width: 34%;
    vertical-align: bottom;
    text-align: center;
}
.footer-right {
    width: 33%;
    vertical-align: bottom;
    text-align: right;
}

.award-badge {
    height: 52px;
    width: auto;
}

.sig-img {
    width: 160px;
    height: auto;
    margin-bottom: 6px;
}
.sig-underline {
    width: 260px;
    height: 1px;
    background: #bbb;
    margin: 0 auto 6px auto;
}
.sig-title {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 400;
    color: #616060;
    letter-spacing: 1px;
    text-transform: uppercase;
    line-height: 1.6;
}

.meta-line {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 400;
    color: #616060;
    letter-spacing: 1px;
    line-height: 1.9;
}
.meta-line strong {
    font-weight: bold;
    color: #616060;
}
</style>
</head>
<body>

<!-- Decorative borders -->
<div class="border-outer"></div>
<div class="border-inner"></div>

<!-- Corner accents -->
<div class="corner-h c-tl-h"></div>
<div class="corner-v c-tl-v"></div>
<div class="corner-h c-tr-h"></div>
<div class="corner-v c-tr-v"></div>
<div class="corner-h c-bl-h"></div>
<div class="corner-v c-bl-v"></div>
<div class="corner-h c-br-h"></div>
<div class="corner-v c-br-v"></div>

<div class="main-content">

    <!-- Logo -->
    <img src="{{ public_path('images/certificado3.png') }}" alt="Runway 7" class="logo-img">

    <!-- Title -->
    <div class="cert-title">Volunteer Achievement Certificate</div>
    <div class="cert-subtitle">{{ strtoupper($event->name) }}</div>

    <div class="gold-divider"></div>

    <!-- Recipient -->
    <div class="presented-label">Presented with recognition to:</div>
    <div class="recipient-name">{{ $volunteer->first_name }} {{ $volunteer->last_name }}</div>
    <div class="name-underline"></div>

    <!-- Message -->
    <div class="message-text">
        This certificate is awarded in recognition of valuable volunteer contribution
        with Runway 7. Through dedication, professionalism, and teamwork, you played
        an essential role in supporting event operations and ensuring a seamless
        experience for participants. Your reliability, collaboration, and positive
        attitude greatly contributed to the success of the event and helped create
        lasting memories for everyone involved.
    </div>

    <!-- Footer -->
    <table class="footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-left">
                <img class="award-badge" src="{{ public_path('images/certificado2.png') }}" alt="">
            </td>
            <td class="footer-center">
                <img class="sig-img" src="{{ public_path('images/certificado1.png') }}" alt="">
                <div class="sig-underline"></div>
                <div class="sig-title">Partners &amp; Designers Relations Director</div>
            </td>
            <td class="footer-right">
                <div class="meta-line"><strong>Certificate ID:</strong> VOL-{{ date('Y') }}-{{ str_pad($volunteer->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div class="meta-line"><strong>Date of Issue:</strong> {{ \Carbon\Carbon::now()->format('F j, Y') }}</div>
            </td>
        </tr>
    </table>

</div>

</body>
</html>
