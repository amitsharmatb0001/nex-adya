<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\RestaurantOwner;
use Illuminate\Support\Facades\Log;

/**
 * EnsureRole middleware
 *
 * - Checks user's role stored on users.role OR checks restaurant_owners table for restaurant roles.
 * - Returns JSON 403 for API requests, not plain exceptions.
 *
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:restaurant,owner')
 * The parameter will be a comma separated list of allowed roles.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $roles = null)
    {
        $user = $request->user();

        // If no user (not authenticated) -> 401
        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.']
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Normalize allowed roles
        $allowed = array_filter(array_map('trim', explode(',', (string) $roles)));

        // If user role is present and in allowed -> allow
        if ($user->role && in_array($user->role, $allowed, true)) {
            return $next($request);
        }

        // Special handling: if allowed includes 'restaurant', also check restaurant_owners table
        if (in_array('restaurant', $allowed, true)) {
            try {
                $owner = RestaurantOwner::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                if ($owner) {
                    // optionally check sub-role like owner/manager/staff if specified
                    if (count($allowed) > 1) {
                        // allowed may be like 'restaurant,owner' meaning restaurant users with owner sub-role
                        // find any subrole intersection
                        $subAllowed = array_diff($allowed, ['restaurant']);
                        if (!empty($subAllowed) && in_array($owner->role, $subAllowed, true)) {
                            return $next($request);
                        }
                        // if no subrole requirement, accept
                    } else {
                        return $next($request);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('EnsureRole: restaurant_owners lookup failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'ok' => false,
                    'error' => ['code' => 'SERVER_ERROR', 'message' => 'Role check failed (db)'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json([
            'ok' => false,
            'error' => ['code' => 'FORBIDDEN', 'message' => 'You are not allowed to perform this action.']
        ], Response::HTTP_FORBIDDEN);
    }
}
