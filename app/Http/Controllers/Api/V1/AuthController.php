<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        $user = Auth::user();

        if ($user->status === 'inactive') {
            Auth::logout();
            return response()->json(['message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'], 403);
        }

        if ($user->status === 'pending') {
            $user->update(['status' => 'active', 'last_login_at' => now()]);
        } else {
            $user->update(['last_login_at' => now()]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user->load(['modelProfile', 'designerProfile']),
            'token' => $token,
        ]);
    }

    public function loginWithCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = User::where('login_code', $request->code)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Código inválido o usuario inactivo.'], 401);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('kiosk-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('modelProfile'),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['modelProfile', 'designerProfile']);
        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada exitosamente.']);
    }
}
