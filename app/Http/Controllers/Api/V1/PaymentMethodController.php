<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethodConfig;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $methods = PaymentMethodConfig::active()
            ->ordered()
            ->get(['id', 'name', 'label', 'type', 'config', 'logo_url', 'qr_image_url']);

        return response()->json(['data' => $methods]);
    }
}
