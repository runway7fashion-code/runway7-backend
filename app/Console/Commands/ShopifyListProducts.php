<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShopifyListProducts extends Command
{
    protected $signature = 'shopify:list-products {--status=any : draft, active, archived, any}';
    protected $description = 'Lista productos y variantes de Shopify con sus IDs';

    public function handle(): int
    {
        $shop    = config('services.shopify.shop_domain');
        $token   = config('services.shopify.access_token');
        $version = config('services.shopify.api_version', '2025-01');

        if (!$shop || !$token) {
            $this->error('SHOPIFY_SHOP_DOMAIN o SHOPIFY_ACCESS_TOKEN no están configurados en .env');
            return self::FAILURE;
        }

        $status = $this->option('status');
        $this->info("Fetching products from {$shop} (status={$status})...");
        $this->newLine();

        $url = "https://{$shop}/admin/api/{$version}/products.json";
        $params = ['limit' => 250, 'fields' => 'id,title,status,variants'];
        if ($status !== 'any') $params['status'] = $status;

        try {
            $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])->get($url, $params);

            if ($response->failed()) {
                $this->error("Shopify API error: {$response->status()}");
                $this->line($response->body());
                return self::FAILURE;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                $this->warn('No products returned.');
                return self::SUCCESS;
            }

            foreach ($products as $product) {
                $this->line("<fg=yellow;options=bold>{$product['title']}</> <fg=gray>[status: {$product['status']}, product_id: {$product['id']}]</>");

                foreach ($product['variants'] as $variant) {
                    $title = $variant['title'] === 'Default Title' ? '(default)' : $variant['title'];
                    $price = '$' . number_format((float) $variant['price'], 2);
                    $sku   = $variant['sku'] ?: '-';
                    $this->line("  variant_id: <fg=green>{$variant['id']}</>  |  {$title}  |  {$price}  |  sku: {$sku}");
                }
                $this->newLine();
            }

            $this->info('Total products: ' . count($products));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Exception: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
