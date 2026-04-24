<?php

/**
 * Mapeo de cuentas Zoho para el equipo de Sponsorship.
 *
 * Cada asesor envía emails desde su propia cuenta Zoho (vía Mailgun como transport).
 * Luego de enviar, hacemos IMAP APPEND a la carpeta "Sent" de su cuenta Zoho
 * para que el email aparezca como enviado en su bandeja.
 *
 * Las contraseñas son App Passwords de Zoho (no la contraseña de login).
 */
return [

    'host'        => env('ZOHO_IMAP_HOST', 'imap.zoho.com'),
    'port'        => (int) env('ZOHO_IMAP_PORT', 993),
    'encryption'  => env('ZOHO_IMAP_ENCRYPTION', 'ssl'),
    'sent_folder' => env('ZOHO_IMAP_SENT_FOLDER', 'Sent'),

    /**
     * Map: email (lowercased) → App Password (env).
     * La lookup en el servicio lowercasea ambos lados para que sea case-insensitive.
     */
    'accounts' => [
        'sponsorsrelations@runway7fashion.com' => env('ZOHO_IMAP_PASS_SPONSORSRELATIONS'),
        'sponsors@runway7fashion.com'          => env('ZOHO_IMAP_PASS_SPONSORS'),
        'partenrships@runway7fashion.com'      => env('ZOHO_IMAP_PASS_PARTENRSHIPS'),
        'sponsorships@runway7fashion.com'      => env('ZOHO_IMAP_PASS_SPONSORSHIPS'),
        'partners@runway7fashion.com'          => env('ZOHO_IMAP_PASS_PARTNERS'),
    ],
];
