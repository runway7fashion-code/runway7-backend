<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackUserPresence
{
    /**
     * Update last_seen_at for the authenticated user. Throttled — at most once per 30s per user
     * to avoid an UPDATE on every API request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && (!$user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(30)))) {
            DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
            $user->last_seen_at = now();
        }

        return $next($request);
    }
}
