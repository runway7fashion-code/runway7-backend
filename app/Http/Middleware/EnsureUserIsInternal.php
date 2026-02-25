<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsInternal
{
    private const INTERNAL_ROLES = [
        'admin',
        'accounting',
        'operation',
        'tickets_manager',
        'marketing',
        'public_relations',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, self::INTERNAL_ROLES)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}
