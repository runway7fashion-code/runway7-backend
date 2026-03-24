<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
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
     * Detects if the buyer is a model and processes accordingly:
     * - Rejected model → auto-assign runway_merch tag, reactivate, assign merch slot
     * - Accepted model without merch → mark pass as preferential
     */
    public function orderPaid(Request $request): JsonResponse
    {
        // Verify Shopify HMAC signature
        if (!$this->verifyWebhook($request)) {
            Log::warning('Shopify webhook: invalid HMAC signature');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $email = strtolower(trim($payload['email'] ?? $payload['customer']['email'] ?? ''));
        $orderNumber = $payload['order_number'] ?? $payload['name'] ?? null;

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
