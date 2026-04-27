<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\Sponsorship\LeadActivityDeliveryUpdated;
use App\Http\Controllers\Controller;
use App\Models\Sponsorship\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe eventos de Mailgun (delivered, failed, complained, etc.) y actualiza
 * el estado de entrega de las actividades de email de sponsorship.
 *
 * Setup en Mailgun (Domains → Webhooks):
 *   URL:  https://runways7.com/api/v1/mailgun/webhook
 *   Eventos: "Permanent Failure", "Temporary Failure", "Delivered Message", "Spam Complaints"
 *
 * Configurar ENV:
 *   MAILGUN_WEBHOOK_SIGNING_KEY=...  (lo obtenés de Mailgun dashboard → HTTP webhook signing key)
 */
class MailgunWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Verificar firma HMAC para asegurar que el request viene de Mailgun.
        $signing = (array) $request->input('signature', []);
        $timestamp = $signing['timestamp'] ?? null;
        $token     = $signing['token']     ?? null;
        $signature = $signing['signature'] ?? null;

        if (!$this->verifySignature($timestamp, $token, $signature)) {
            Log::warning('[Mailgun Webhook] Invalid signature', ['ts' => $timestamp]);
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        // 2. Parsear evento.
        $eventData = (array) $request->input('event-data', []);
        $event     = $eventData['event'] ?? null;
        $severity  = $eventData['severity'] ?? null;
        $rawMsgId  = $eventData['message']['headers']['message-id'] ?? null;
        $reason    = $eventData['delivery-status']['description']
                    ?? $eventData['delivery-status']['message']
                    ?? $eventData['reason']
                    ?? null;

        if (!$event || !$rawMsgId) {
            return response()->json(['ok' => true, 'ignored' => 'missing event or message-id']);
        }

        $messageId = trim((string) $rawMsgId, '<> ');

        $activity = LeadActivity::where('mailgun_message_id', $messageId)->first();
        if (!$activity) {
            // No es un email de sponsorship (otros módulos podrían usar Mailgun también) — ignorar.
            return response()->json(['ok' => true, 'ignored' => 'unknown message-id']);
        }

        // 3. Aplicar el evento al activity.
        $changed = false;
        switch ($event) {
            case 'delivered':
                $activity->update([
                    'delivery_status' => 'delivered',
                    'delivered_at'    => now(),
                    // No tocamos status aquí — sigue siendo 'completed'.
                ]);
                $changed = true;
                break;

            case 'failed':
                // Mailgun usa severity=permanent (hard bounce) vs temporary.
                if ($severity === 'permanent') {
                    $activity->update([
                        'delivery_status' => 'bounced',
                        'delivery_error'  => $reason,
                        'status'          => 'not_completed',
                    ]);
                } else {
                    // Temporary failure — reintentarán, solo lo registramos.
                    $activity->update([
                        'delivery_status' => 'temporary_fail',
                        'delivery_error'  => $reason,
                    ]);
                }
                $changed = true;
                break;

            case 'complained':
                $activity->update([
                    'delivery_status' => 'complained',
                    'delivery_error'  => $reason ?? 'User marked as spam',
                    'status'          => 'not_completed',
                ]);
                $changed = true;
                break;

            case 'rejected':
                // Mailgun rechazó el envío (ej. address invalid before sending).
                $activity->update([
                    'delivery_status' => 'rejected',
                    'delivery_error'  => $reason,
                    'status'          => 'not_completed',
                ]);
                $changed = true;
                break;

            default:
                // Otros eventos (opened, clicked, unsubscribed) — por ahora los ignoramos.
                break;
        }

        // Notificar a la pantalla del lead vía Reverb para refrescar sin reload.
        if ($changed) {
            broadcast(new LeadActivityDeliveryUpdated($activity->fresh()));
        }

        return response()->json(['ok' => true, 'event' => $event, 'activity_id' => $activity->id]);
    }

    /**
     * Verifica la firma HMAC-SHA256 que Mailgun envía.
     * Además chequea que el timestamp no sea mayor a 5 min (anti-replay).
     */
    private function verifySignature(?string $timestamp, ?string $token, ?string $signature): bool
    {
        $key = config('services.mailgun.webhook_signing_key');
        if (!$key || !$timestamp || !$token || !$signature) {
            return false;
        }

        // Anti-replay: rechazar firmas viejas o del futuro.
        $age = abs(time() - (int) $timestamp);
        if ($age > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $key);
        return hash_equals($expected, $signature);
    }
}
