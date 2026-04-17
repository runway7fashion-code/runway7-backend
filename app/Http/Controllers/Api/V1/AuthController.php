<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected ActivityLogService $activityLog) {}

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

        if ($user->status === 'rejected') {
            Auth::logout();
            return response()->json(['message' => 'Tu aplicación ha sido rechazada. Contacta al administrador para más información.'], 403);
        }

        if ($user->status === 'applicant') {
            Auth::logout();
            return response()->json(['message' => 'Tu aplicación está siendo revisada. Te notificaremos cuando sea aprobada.'], 403);
        }

        if ($user->status === 'pending') {
            $user->update(['status' => 'active', 'last_login_at' => now()]);

            // Auto-confirmed: actualizar sales_registration al primer login
            if ($user->role === 'designer') {
                \App\Models\SalesRegistration::where('designer_id', $user->id)
                    ->where('status', 'onboarded')
                    ->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            }
        } else {
            $user->update(['last_login_at' => now()]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->activityLog->log(ActivityAction::Login, $user, $user, "Login vía app ({$user->role})");

        return response()->json([
            'user' => $user->load(['modelProfile', 'designerProfile.category']),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['modelProfile', 'designerProfile.category']);
        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada exitosamente.']);
    }
}
