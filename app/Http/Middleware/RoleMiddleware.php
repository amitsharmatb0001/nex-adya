<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'You must be logged in.'
                ]
            ], 401);
        }


        if (!in_array($user->role, $roles)) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You are not allowed to perform this action.'
                ]
            ], 403);
        }

        return $next($request);
    }
}
