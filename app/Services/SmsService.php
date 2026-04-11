<?php

namespace App\Services;

use Twilio\Rest\Client;

class SmsService
{
    // Default US pricing per segment (Twilio standard rate ~$0.0083 as of 2024-2025)
    const PRICE_PER_SEGMENT_US = 0.0083;

    // GSM-7 character set pattern
    const GSM7_REGEX = '/^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#$%&\'()*+,\-.\/:;<=>?¡¿\^\{\}\[\]~\|€]+$/u';

    protected ?Client $client = null;

    protected function client(): Client
    {
        if (!$this->client) {
            $this->client = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token'),
            );
        }
        return $this->client;
    }

    /**
     * Detect if a message can be sent as GSM-7 (160 chars per segment)
     * or needs UCS-2 (70 chars per segment, for emojis/special chars).
     */
    public function isGsm7(string $message): bool
    {
        return preg_match(self::GSM7_REGEX, $message) === 1;
    }

    /**
     * Calculate the number of SMS segments for a given message.
     */
    public function calculateSegments(string $message): int
    {
        if ($message === '') return 0;
        $length = mb_strlen($message);

        if ($this->isGsm7($message)) {
            // GSM-7: 160 chars for single, 153 for multi-segment
            if ($length <= 160) return 1;
            return (int) ceil($length / 153);
        }

        // UCS-2: 70 chars for single, 67 for multi-segment
        if ($length <= 70) return 1;
        return (int) ceil($length / 67);
    }

    /**
     * Validate phone number is in E.164 format (+[country code][number]).
     */
    public function isValidE164(?string $phone): bool
    {
        if (!$phone) return false;
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }

    /**
     * Normalize phone to E.164 format.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $phone = preg_replace('/[\s\(\)\-]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $this->isValidE164($phone) ? $phone : null;
    }

    /**
     * Estimate cost in USD for sending a message to N recipients.
     */
    public function estimateCost(string $message, int $recipientsCount): array
    {
        $segments = $this->calculateSegments($message);
        $perSmsCost = $segments * self::PRICE_PER_SEGMENT_US;
        $totalCost = $perSmsCost * $recipientsCount;

        return [
            'segments'     => $segments,
            'encoding'     => $this->isGsm7($message) ? 'GSM-7' : 'UCS-2',
            'per_sms_cost' => round($perSmsCost, 4),
            'total_cost'   => round($totalCost, 4),
            'recipients'   => $recipientsCount,
        ];
    }

    /**
     * Get current Twilio account balance.
     */
    public function getBalance(): ?array
    {
        try {
            $balance = $this->client()->balance->fetch();
            return [
                'balance'  => round((float) $balance->balance, 2),
                'currency' => $balance->currency,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Replace variables in message body for a given user.
     * Supports: {{first_name}}, {{last_name}}, {{full_name}}, {{email}}, {{phone}}
     */
    public function replaceVariables(string $message, $user): string
    {
        $replacements = [
            '{{first_name}}' => $user->first_name ?? '',
            '{{last_name}}'  => $user->last_name ?? '',
            '{{full_name}}'  => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            '{{email}}'      => $user->email ?? '',
            '{{phone}}'      => $user->phone ?? '',
        ];

        return strtr($message, $replacements);
    }

    /**
     * Available variables for the frontend to offer as insertable placeholders.
     */
    public static function availableVariables(): array
    {
        return [
            ['key' => '{{first_name}}', 'label' => 'First Name'],
            ['key' => '{{last_name}}',  'label' => 'Last Name'],
            ['key' => '{{full_name}}',  'label' => 'Full Name'],
            ['key' => '{{email}}',      'label' => 'Email'],
            ['key' => '{{phone}}',      'label' => 'Phone'],
        ];
    }

    /**
     * Append Runway 7 signature to the message.
     */
    public function appendSignature(string $message): string
    {
        $signature = "\n- Runway 7";
        if (str_ends_with(trim($message), '- Runway 7')) {
            return $message;
        }
        return rtrim($message) . $signature;
    }
}
