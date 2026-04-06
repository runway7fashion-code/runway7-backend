<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HomeCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cards = HomeCard::active()
            ->forRole($user->role)
            ->orderBy('order')
            ->get(['id', 'title', 'image_url', 'action_type', 'action_value', 'order']);

        return response()->json(['data' => $cards]);
    }
}
