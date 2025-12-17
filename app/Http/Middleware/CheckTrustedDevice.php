<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TrustedDeviceService;
use App\Services\ActivityLogService;

class CheckTrustedDevice
{
    protected TrustedDeviceService $service;

    public function __construct(TrustedDeviceService $service)
    {
        $this->service = $service;
    }

    /**
     * If cookie present and valid, attach user and bypass 2FA for this request.
     */
    public function handle(Request $request, Closure $next)
    {
        $cookie = $request->cookie(TrustedDeviceService::COOKIE_NAME) ?? $request->header('X-Trusted-Device');

        if ($cookie) {
            $device = $this->service->findByPayload($cookie);
            
            // Check if device exists and is valid
            if (!$device) {
                ActivityLogService::log('trusted_cookie_invalid', [
                    'ip' => $request->ip(),
                    'reason' => 'device_not_found'
                ]);
                return response()->json(['error' => 'Invalid trusted device'], 401);
            }
            
            // Check if device is active
            if (!$device->is_active) {
                ActivityLogService::log('trusted_cookie_invalid', [
                    'ip' => $request->ip(),
                    'reason' => 'device_inactive'
                ]);
                return response()->json(['error' => 'Trusted device is inactive'], 401);
            }
            
            // Check if device is expired
            if ($device->expires_at && $device->expires_at->isPast()) {
                ActivityLogService::log('trusted_cookie_invalid', [
                    'ip' => $request->ip(),
                    'reason' => 'device_expired'
                ]);
                return response()->json(['error' => 'Trusted device has expired'], 401);
            }
            
            if ($device && $device->user_id) {
                // attach user to request for auth guard (sanctum will still validate token for APIs)
                // We can set a flag so controllers know 2FA was bypassed by trusted device.
                $request->attributes->set('trusted_device', $device);
                $this->service->touch($device, $request, false);
                ActivityLogService::log('trusted_cookie_valid', [
                    'user_id' => $device->user_id,
                    'device_id' => $device->id,
                    'ip' => $request->ip()
                ]);
            } else {
                ActivityLogService::log('trusted_cookie_invalid', [
                    'ip' => $request->ip(),
                    'reason' => 'no_user'
                ]);
                return response()->json(['error' => 'Invalid trusted device'], 401);
            }
        }

        return $next($request);
    }
}
