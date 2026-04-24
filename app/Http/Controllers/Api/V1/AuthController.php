<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    /**
     * Generate a 6-digit password reset code, email it to the user, and persist
     * the hashed code for later verification. Always responds 200 — we don't
     * leak whether the email is registered.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower($request->input('email'));
        $user  = User::where('email', $email)->first();

        // Only send the email when the user actually exists. Either way, return
        // the same response so the caller can't enumerate registered emails.
        if ($user) {
            $code = (string) random_int(100000, 999999);

            // Invalidate any previous un-used codes for this email.
            DB::table('password_reset_codes')
                ->where('email', $email)
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);

            DB::table('password_reset_codes')->insert([
                'email'      => $email,
                'code'       => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Mail::to($user->email)->send(new PasswordResetCodeMail($code, $user->first_name));
            } catch (\Throwable $e) {
                Log::warning('Password reset email failed for '.$email.': '.$e->getMessage());
            }
        }

        return response()->json(['message' => 'If the email exists, a code has been sent.']);
    }

    /**
     * Verify the 6-digit code and set the new password. Invalidates all
     * existing Sanctum tokens on success so other devices are forced to re-login.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email',
            'code'                  => 'required|digits:6',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower($request->input('email'));
        $user  = User::where('email', $email)->first();

        $candidates = DB::table('password_reset_codes')
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get();

        $match = $candidates->first(fn ($row) => Hash::check($request->input('code'), $row->code));

        if (!$user || !$match) {
            return response()->json(['message' => 'Invalid or expired code'], 422);
        }

        DB::transaction(function () use ($user, $match, $request) {
            $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

            DB::table('password_reset_codes')
                ->where('id', $match->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);

            // Force re-login on every other device.
            $user->tokens()->delete();
        });

        $this->activityLog->log(ActivityAction::PasswordReset, $user, $user, 'Password reset vía app');

        return response()->json(['message' => 'Password reset successfully']);
    }
}
