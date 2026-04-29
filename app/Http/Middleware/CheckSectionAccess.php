<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSectionAccess
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return $next($request);
        }

        $permissions = config("role_permissions.{$user->role}.sections", []);
        // Cross-area access: incluir secciones de áreas secundarias (extra_areas).
        foreach ((array) ($user->extra_areas ?? []) as $extraRole) {
            $permissions = array_merge($permissions, config("role_permissions.{$extraRole}.sections", []));
        }

        if (!in_array($section, $permissions)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes acceso a esta sección.'], 403);
            }
            abort(403, 'No tienes acceso a esta sección.');
        }

        return $next($request);
    }
}
