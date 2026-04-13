<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    private $messaging;

    public function __construct()
    {
        $credentialsPath = config('firebase.credentials');

        if (file_exists($credentialsPath)) {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        }
    }

    /**
     * Enviar notificación a un usuario específico (todos sus dispositivos).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificación a todos los usuarios de un rol.
     */
    public function sendToRole(string $role, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::whereHas('user', fn ($q) => $q->where('role', $role))
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificación a múltiples roles.
     */
    public function sendToRoles(array $roles, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::whereHas('user', fn ($q) => $q->whereIn('role', $roles))
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Enviar a una lista de tokens FCM.
     */
    private function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (!$this->messaging) {
            Log::warning('Firebase messaging not configured. Skipping push notification.');
            return 0;
        }

        $notification = Notification::create($title, $body);

        // Configure APNs headers for iOS delivery
        $apnsConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
                'apns-push-type' => 'alert',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'sound' => 'default',
                    'content-available' => 1,
                ],
            ],
        ]);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withApnsConfig($apnsConfig);

        if (!empty($data)) {
            $message = $message->withData($data);
        }

        // Firebase permite max 500 tokens por envío
        $sent = 0;
        $invalidTokens = [];

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $report = $this->messaging->sendMulticast($message, $chunk);
                $sent += $report->successes()->count();

                // Recopilar tokens inválidos para desactivarlos
                foreach ($report->failures()->getItems() as $failure) {
                    $invalidTokens[] = $failure->target()->value();
                }
            } catch (\Throwable $e) {
                Log::error('Firebase push notification error: ' . $e->getMessage());
            }
        }

        // Desactivar tokens inválidos
        if (!empty($invalidTokens)) {
            DeviceToken::whereIn('token', $invalidTokens)->update(['is_active' => false]);
        }

        return $sent;
    }
}
