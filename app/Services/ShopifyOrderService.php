<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyOrderService
{
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;

    public function __construct()
    {
        $this->shopDomain = config('services.shopify.shop_domain');
        $this->accessToken = config('services.shopify.access_token');
        $this->apiVersion = config('services.shopify.api_version', '2025-01');
    }

    /**
     * Valida que una orden de Shopify existe y ha sido pagada.
     *
     * @return array{valid: bool, order: ?array, reason: ?string}
     */
    public function validatePaidOrder(string $orderNumber): array
    {
        $orderNumber = ltrim(trim($orderNumber), '#');

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->get("https://{$this->shopDomain}/admin/api/{$this->apiVersion}/orders.json", [
                'name' => $orderNumber,
                'status' => 'any',
                'fields' => 'id,name,email,financial_status,total_price,confirmed,created_at',
            ]);

            if ($response->failed()) {
                Log::error("Shopify API error: {$response->status()} - {$response->body()}");
                return [
                    'valid' => false,
                    'order' => null,
                    'reason' => 'Unable to verify order. Please try again later.',
                ];
            }

            $orders = $response->json('orders', []);

            if (empty($orders)) {
                return [
                    'valid' => false,
                    'order' => null,
                    'reason' => 'Order not found. Please check your order number and try again.',
                ];
            }

            $order = $orders[0];
            $paidStatuses = ['paid', 'partially_refunded'];

            if (!in_array($order['financial_status'], $paidStatuses)) {
                return [
                    'valid' => false,
                    'order' => $order,
                    'reason' => 'Order found but payment has not been completed.',
                ];
            }

            return [
                'valid' => true,
                'order' => $order,
                'reason' => null,
            ];
        } catch (\Exception $e) {
            Log::error("Shopify API exception: {$e->getMessage()}");
            return [
                'valid' => false,
                'order' => null,
                'reason' => 'Unable to verify order. Please try again later.',
            ];
        }
    }
}
