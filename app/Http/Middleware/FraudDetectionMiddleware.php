<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\FraudAuditLog;
use Illuminate\Support\Str;

/**
 * FraudDetectionMiddleware
 * 
 * Implements multi-layer fraud detection:
 * - Rate limiting
 * - Device fingerprinting (NO BYPASS)
 * - User agent tracking
 * - IP monitoring
 */
class FraudDetectionMiddleware
{
    public function handle(Request $request, Closure $next, ...$params)
    {
        try {
            $type = $params[0] ?? null;
            $ip = $request->ip();
            $userId = $request->user()?->id;

            // Skip for OPTIONS requests
            if ($request->isMethod('OPTIONS')) {
                return $next($request);
            }

            if (!$type) {
                return $next($request);
            }

            return $this->performFraudCheck($request, $userId, $ip, $type, $next);
        } catch (\Throwable $e) {
            Log::error('Fraud detection middleware error: ' . $e->getMessage(), [
                'user_id' => $userId ?? null,
                'ip' => $ip ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // On error, allow the request to proceed but log it
            return $next($request);
        }
    }

    private function performFraudCheck(Request $request, $userId, $ip, $type, Closure $next)
    {
        // Device and User-Agent checks (for authenticated users)
        if ($userId) {
            $deviceCheck = $this->checkDevice($userId, $ip, $request);
            if ($deviceCheck !== true) {
                return $deviceCheck;
            }

            $userAgentCheck = $this->checkUserAgent($userId, $request->header('User-Agent'), $ip, $request);
            if ($userAgentCheck !== true) {
                return $userAgentCheck;
            }
        }

        // Fraud detection logic based on $type
        if ($type === 'login') {
            return $this->handleLoginFraud($userId, $ip, $request, $next);
        } elseif ($type === 'order') {
            return $this->handleOrderFraud($userId, $ip, $request, $next);
        } elseif ($type === 'payment') {
            return $this->handlePaymentFraud($userId, $ip, $request, $next);
        }

        return $next($request);
    }

    private function handleLoginFraud($userId, $ip, Request $request, Closure $next)
    {
        $failedAttempts = Cache::get("failed_logins:{$userId}:{$ip}", 0);
        
        if ($failedAttempts >= 5) {
            $this->logFraud($userId, 'login_block', $ip, 'Too many failed login attempts');
            return response()->json([
                'ok' => false,
                'error' => 'Too many failed login attempts. Try again later.'
            ], 429);
        }
        
        return $next($request);
    }

    private function handleOrderFraud($userId, $ip, Request $request, Closure $next)
    {
        $orderTotal = $request->input('total', 0);
        $recentOrders = Cache::get("recent_orders:{$userId}", 0);
        
        // Check for suspicious order patterns
        if ($orderTotal > 1000 && $recentOrders > 3) {
            $this->logFraud($userId, 'order_suspicious', $ip, 'High value order pattern detected');
            return response()->json([
                'ok' => false,
                'error' => 'Order requires manual review.'
            ], 403);
        }
        
        return $next($request);
    }

    private function handlePaymentFraud($userId, $ip, Request $request, Closure $next)
    {
        $fails = Cache::get("payment_fails:{$userId}:{$ip}", 0);
        
        if ($fails >= 3) {
            $this->logFraud($userId, 'payment_block', $ip, 'Too many failed payments');
            return response()->json([
                'ok' => false,
                'error' => 'Too many failed payment attempts. Try later.'
            ], 429);
        }
        
        return $next($request);
    }

    private function isCriticalRoute(Request $request)
    {
        $criticalRoutes = ['/api/payment', '/api/order', '/api/login'];
        return in_array($request->path(), $criticalRoutes);
    }

    private function logFraud($userId, $event, $ip, $message)
    {
        // Use queue for logging to avoid blocking the request
        dispatch(function() use ($userId, $event, $ip, $message) {
            FraudAuditLog::create([
                'user_id' => $userId,
                'event'   => $event,
                'metadata'=> json_encode(['ip' => $ip, 'message' => $message]),
                'status'  => 'blocked',
            ]);
        })->afterResponse();
        
        Log::warning("Fraud detected: $event from IP $ip", [
            'user_id' => $userId,
            'event' => $event,
            'ip' => $ip
        ]);
    }

    /**
     * ✅ FIXED - No bypass: Generate device fingerprint if missing
     */
    private function checkDevice($userId, $ip, $request)
    {
        $deviceHash = $request->header('X-Device-Hash');

        // Cache user data to reduce database queries
        $user = Cache::remember("user:{$userId}", 300, function() use ($userId) {
            return User::select('id', 'last_device_hash', 'last_user_agent')->find($userId);
        });

        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => 'User not found.'
            ], 401);
        }

        // ✅ FIX - Don't skip validation - generate hash from available data
        if (!$deviceHash) {
            $deviceHash = $this->generateDeviceFingerprint($request);
        }

        // First time user - store device hash
        if (!$user->last_device_hash) {
            User::where('id', $userId)->update(['last_device_hash' => $deviceHash]);
            Cache::forget("user:{$userId}");
            return true;
        }

        // Device changed - require re-authentication
        if ($user->last_device_hash !== $deviceHash) {
            $this->logFraud($userId, 'device_change', $ip, 'Device hash mismatch');
            return response()->json([
                'ok' => false,
                'error' => 'Device changed. Please login again.'
            ], 403);
        }

        return true;
    }

    /**
     * ✅ NEW - Generate device fingerprint from browser characteristics
     */
    private function generateDeviceFingerprint(Request $request): string
    {
        // Generate consistent hash from browser fingerprint
        $components = [
            $request->userAgent() ?? '',
            $request->header('Accept-Language') ?? '',
            $request->header('Accept-Encoding') ?? '',
            $request->header('Accept') ?? '',
        ];
        
        return hash('sha256', implode('|', array_filter($components)));
    }

    private function checkUserAgent($userId, $userAgent, $ip, $request)
    {
        // Use cached user data from checkDevice
        $user = Cache::get("user:{$userId}");
        
        if (!$user) {
            $user = Cache::remember("user:{$userId}", 300, function() use ($userId) {
                return User::select('id', 'last_device_hash', 'last_user_agent')->find($userId);
            });
        }

        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => 'User not found.'
            ], 401);
        }

        // Skip user agent check for API requests (Postman, etc.)
        if (!$userAgent || str_contains($userAgent, 'PostmanRuntime') || str_contains($userAgent, 'curl')) {
            return true;
        }

        // Only check if user has a user agent stored
        if ($user->last_user_agent && $user->last_user_agent !== $userAgent) {
            $this->logFraud($userId, 'user_agent_change', $ip, 'User agent changed');
            return response()->json([
                'ok' => false,
                'error' => 'Device changed. Please login again.'
            ], 429);
        }

        // Optionally update user agent if not set
        if (!$user->last_user_agent && $userAgent) {
            User::where('id', $userId)->update(['last_user_agent' => $userAgent]);
            Cache::forget("user:{$userId}");
        }

        return true;
    }
}