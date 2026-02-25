<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $banners = Banner::active()
            ->forRole($user->role)
            ->orderBy('order')
            ->get(['id', 'title', 'image_url', 'link_url', 'order']);

        return response()->json(['data' => $banners]);
    }
}
