<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected Client $client;
    protected string $from;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token'),
        );
        $this->from = config('services.twilio.from');
    }

    public function send(string $to, string $body): void
    {
        $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $body,
        ]);
    }

    public function getBalance(): ?array
    {
        try {
            $balance = $this->client->balance->fetch();
            return [
                'balance'  => number_format((float) $balance->balance, 4),
                'currency' => $balance->currency,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
