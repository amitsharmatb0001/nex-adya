<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Role
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin') or ->middleware('role:restaurant')
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['ok' => false, 'error' => ['message' => 'Unauthenticated']], 401);
        }

        // Admin check (keep simple: users.role === 'admin')
        if ($role === 'admin') {
            if ($user->role === 'admin') {
                return $next($request);
            }
            return response()->json(['ok' => false, 'error' => ['message' => 'Forbidden']], 403);
        }

        // Restaurant role — single source of truth: restaurant_owners table
        if ($role === 'restaurant') {
            // active mapping check
            $hasActiveOwner = \App\Models\RestaurantOwner::where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if ($hasActiveOwner) {
                return $next($request);
            }

            // Backwards compatibility: if user.role === 'restaurant' (older seeds) allow
            if ($user->role === 'restaurant') {
                return $next($request);
            }

            return response()->json(['ok' => false, 'error' => ['message' => 'Forbidden']], 403);
        }

        // Default: check users.role equals required role
        if ($user->role === $role) {
            return $next($request);
        }

        return response()->json(['ok' => false, 'error' => ['message' => 'Forbidden']], 403);
    }
}
