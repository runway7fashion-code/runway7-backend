<?php

namespace App\Support;

class InstagramSanitizer
{
    public static function sanitize(?string $value): ?string
    {
        if (!$value || !trim($value)) {
            return null;
        }

        $ig = strtok(trim($value), '?');
        $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
        $ig = rtrim($ig, '/');
        $ig = ltrim($ig, '@');

        return $ig ?: null;
    }
}
