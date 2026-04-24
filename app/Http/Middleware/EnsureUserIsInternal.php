<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsInternal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, User::ROLES_INTERNAL)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}
