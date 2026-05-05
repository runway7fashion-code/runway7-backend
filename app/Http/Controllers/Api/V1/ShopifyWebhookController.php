<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendMediaRegistrationEmailJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\User;
use App\Notifications\NewMediaRegistered;
use App\Notifications\NewModelRegistered;
use App\Services\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function __construct(protected ModelService $modelService) {}

    /**
     * Handle Shopify "order paid" webhook.
     *
     * Two flows:
     *  1. Media kit purchase: identified by note_attributes.registration_token → confirms a pending media registration
     *  2. Model merch purchase: existing model in DB by email
     *     - Rejected model → auto-assign runway_merch tag, reactivate, assign merch slot
     *     - Accepted model without merch → mark pass as preferential
     */
    public function orderPaid(Request $request): JsonResponse
    {
        // Verify Shopify HMAC signature
        if (!$this->verifyWebhook($request)) {
            Log::warning('Shopify webhook: invalid HMAC signature');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload     = $request->all();
        $email       = strtolower(trim($payload['email'] ?? $payload['customer']['email'] ?? ''));
        $orderNumber = $payload['order_number'] ?? $payload['name'] ?? null;
        $totalPrice  = isset($payload['total_price']) ? (float) $payload['total_price'] : null;

        // Parse note_attributes (Shopify sends them as [{name,value},...])
        $noteAttributes = collect($payload['note_attributes'] ?? [])
            ->mapWithKeys(fn($a) => [$a['name'] ?? '' => $a['value'] ?? null])
            ->all();

        $registrationToken = $noteAttributes['registration_token'] ?? null;

        // Flow 1: media kit purchase (driven by registration_token attribute)
        if ($registrationToken) {
            return $this->handleMediaKitPurchase($registrationToken, $orderNumber, $totalPrice);
        }

        // Flow 2: model merch purchase (driven by buyer email)
        if (!$email) {
            return response()->json(['status' => 'no_email']);
        }

        Log::info("Shopify webhook order-paid: email={$email}, order={$orderNumber}");

        $user = User::where('email', $email)->where('role', 'model')->first();

        if (!$user) {
            Log::info("Shopify webhook: no model found for {$email}");
            return response()->json(['status' => 'not_a_model']);
        }

        $user->load('eventsAsModelWithCasting');

        if ($user->status === 'rejected') {
            $this->handleRejectedModelPurchase($user, $orderNumber);
        } elseif (in_array($user->status, ['pending', 'active'])) {
            $this->handleAcceptedModelPurchase($user, $orderNumber);
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Media kit purchase paid → confirm registration, create pass, send email.
     */
    private function handleMediaKitPurchase(string $registrationToken, ?string $orderNumber, ?float $totalPrice): JsonResponse
    {
        $eventMedia = DB::table('event_media')
            ->where('registration_token', $registrationToken)
            ->first();

        if (!$eventMedia) {
            Log::warning("Shopify webhook media: no event_media found for token {$registrationToken}");
            return response()->json(['status' => 'media_registration_not_found']);
        }

        if ($eventMedia->payment_status === 'paid') {
            Log::info("Shopify webhook media: registration {$eventMedia->id} already paid (idempotent)");
            return response()->json(['status' => 'already_paid']);
        }

        $cleanOrder = $orderNumber ? ltrim(trim((string) $orderNumber), '#') : null;

        DB::table('event_media')
            ->where('id', $eventMedia->id)
            ->update([
                'status'               => 'assigned',
                'payment_status'       => 'paid',
                'shopify_order_number' => $cleanOrder,
                'total_amount'         => $totalPrice ?? $eventMedia->total_amount,
                'paid_at'              => now(),
                'updated_at'           => now(),
            ]);

        $user  = User::find($eventMedia->media_id);
        $event = Event::find($eventMedia->event_id);

        if (!$user || !$event) {
            Log::error("Shopify webhook media: user or event not found for event_media {$eventMedia->id}");
            return response()->json(['status' => 'error']);
        }

        // Activate user (was 'applicant' until payment confirmed)
        if ($user->status === 'applicant') {
            $user->update(['status' => 'active']);
        }

        // Create event pass (5-day kit gets preferential treatment)
        EventPass::create([
            'event_id'        => $event->id,
            'user_id'         => $user->id,
            'qr_code'         => EventPass::generateQrCode(),
            'pass_type'       => 'media',
            'holder_name'     => $user->full_name,
            'holder_email'    => $user->email,
            'issued_at'       => now(),
            'status'          => 'active',
            'is_preferential' => $eventMedia->kit_type === '5_day',
        ]);

        // Email + admin notification
        $log = CommunicationLog::create([
            'user_id' => $user->id,
            'sent_by' => null,
            'type'    => 'email',
            'channel' => 'media_registration',
            'status'  => 'queued',
        ]);
        SendMediaRegistrationEmailJob::dispatch($user->id, $event->name, logId: $log->id);

        $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new NewMediaRegistered($user, $event->name));
        }

        Log::info("Shopify webhook media: registration {$eventMedia->id} confirmed paid (order #{$cleanOrder})");

        return response()->json(['status' => 'media_kit_paid']);
    }

    /**
     * Rejected model buys merch → reactivate with runway_merch tag.
     */
    private function handleRejectedModelPurchase(User $user, ?string $orderNumber): void
    {
        // Find the most recently rejected event
        $rejectedEvent = $user->eventsAsModelWithCasting
            ->filter(fn($e) => $e->pivot->status === 'rejected')
            ->sortByDesc(fn($e) => $e->pivot->updated_at)
            ->first();

        if (!$rejectedEvent) return;

        $cleanOrder = $orderNumber ? ltrim(trim((string) $orderNumber), '#') : null;

        // Update pivot: reactivate + assign tag + store order number
        DB::table('event_model')
            ->where('model_id', $user->id)
            ->where('event_id', $rejectedEvent->id)
            ->update([
                'status'               => 'invited',
                'casting_status'       => 'scheduled',
                'model_tag'            => 'runway_merch',
                'shopify_order_number' => $cleanOrder,
            ]);

        // Auto-assign merch casting slot
        $this->modelService->autoAssignCastingSlot($user, $rejectedEvent->id, startFromPosition: 1, slotType: 'merch');

        // Change user status to pending
        $user->update(['status' => 'pending']);

        // Notify operation
        $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new NewModelRegistered($user, $rejectedEvent->name, true));
        }

        Log::info("Shopify webhook: rejected model {$user->id} reactivated with runway_merch for event {$rejectedEvent->id}");
    }

    /**
     * Accepted model buys merch → mark pass as preferential.
     */
    private function handleAcceptedModelPurchase(User $user, ?string $orderNumber): void
    {
        // Find the nearest event (by start_date) where the model is active
        $event = $user->eventsAsModelWithCasting
            ->filter(fn($e) => $e->pivot->status !== 'rejected')
            ->sortBy('start_date')
            ->first();

        if (!$event) return;

        $cleanOrder = $orderNumber ? ltrim(trim((string) $orderNumber), '#') : null;

        // Store order number if not already present
        if ($cleanOrder && !$event->pivot->shopify_order_number) {
            DB::table('event_model')
                ->where('model_id', $user->id)
                ->where('event_id', $event->id)
                ->update(['shopify_order_number' => $cleanOrder]);
        }

        // Mark pass as preferential
        DB::table('event_passes')
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->update(['is_preferential' => true]);

        // Notify operation
        $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new NewModelRegistered($user, $event->name, true));
        }

        Log::info("Shopify webhook: accepted model {$user->id} got preferential pass for event {$event->id}");
    }

    /**
     * Verify Shopify webhook HMAC signature.
     */
    private function verifyWebhook(Request $request): bool
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) return false;

        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        if (!$hmacHeader) return false;

        $calculatedHmac = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($calculatedHmac, $hmacHeader);
    }
}
