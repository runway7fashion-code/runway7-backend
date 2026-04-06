<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Registrar o actualizar un FCM device token.
     */
    public function registerToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:ios,android,web',
        ]);

        $user = $request->user();

        DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $request->input('token'),
            ],
            [
                'platform' => $request->input('platform'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token registrado.']);
    }

    /**
     * Eliminar un device token (al logout o desinstalar).
     */
    public function removeToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json(['message' => 'Token eliminado.']);
    }
}
