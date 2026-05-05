<?php

/**
 * Media Kit & Add-ons catalog for shoprunway7.com
 *
 * Variant IDs come from Shopify Admin (`php artisan shopify:list-products`).
 * Update this file if products change in Shopify.
 */

return [
    'kits' => [
        '1_day' => [
            'name'              => '1-Day Access',
            'price'             => 9.99,
            'shopify_variant_id' => '53142860988710',
            'description'       => 'T-shirt (1 day), Gifting bag, Lanyard',
            'allowed_addons'    => ['lunch_box', 'wifi', 'skip_line'],
        ],
        '5_day' => [
            'name'              => '5-Day Access',
            'price'             => 39.99,
            'shopify_variant_id' => '53142863610150',
            'description'       => 'T-shirt (5 days), Gifting bag, Lanyard, Daily lunch box',
            'allowed_addons'    => ['wifi', 'skip_line'], // lunch box already included
        ],
    ],

    'addons' => [
        'lunch_box' => [
            'name'              => 'Lunch Box',
            'price'             => 10.00,
            'shopify_variant_id' => '53142900965670',
            'description'       => 'Per day',
        ],
        'wifi' => [
            'name'              => 'Wi-Fi Access',
            'price'             => 4.99,
            'shopify_variant_id' => '53142904013094',
            'description'       => 'Event-wide Wi-Fi access',
        ],
        'skip_line' => [
            'name'              => 'Skip-the-Line Access',
            'price'             => 4.99,
            'shopify_variant_id' => '53142915907878',
            'description'       => 'Priority entry to shows',
        ],
    ],
];
