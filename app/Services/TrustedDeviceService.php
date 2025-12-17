<?php

namespace App\Services;

use App\Models\TrustedDevice;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrustedDeviceService
{
    public const COOKIE_NAME = 'trusted_device';
    protected int $defaultDays = 365;

    /**
     * Create a trusted device entry for $user.
     * Returns: [id, rawToken, expiresAt]
     */
    public function createForUser($user, Request $request, ?string $deviceName = null, int $days = null): array
    {
        $days = $days ?? $this->defaultDays;
        $expiresAt = Carbon::now()->addDays($days);

        // raw token contains two parts: id + secret. We expose raw secret to client (store hash server-side).
        $rawId = Str::random(12);
        $rawSecret = Str::random(64);
        $rawToken = "{$rawId}.{$rawSecret}";

        $hash = hash_hmac('sha256', $rawSecret, config('app.key'));

        $t = TrustedDevice::create([
            'user_id'     => $user->id,
            'device_name' => $deviceName ?? request()->header('User-Agent', 'unknown'),
            'token_hash'  => $hash,
            'raw_token_id'=> $rawId,
            'expires_at'  => $expiresAt,
            'ip_address'  => $request->ip(),
            'user_agent'  => substr($request->userAgent() ?? '', 0, 1000),
            'is_active'   => true,
        ]);

        // Return (id, raw token to be stored in cookie, expires)
        return [$t->id, $rawToken, $expiresAt];
    }

    /**
     * Validate cookie payload e.g. base64(id|token) or id|token
     * Returns TrustedDevice|null
     */
    public function findByPayload(?string $payload): ?TrustedDevice
    {
        if (!$payload) {
            return null;
        }

        // Accept either base64('id|token') or 'id|token'
        $decoded = $payload;
        if (!str_contains($payload, '|')) {
            $decoded = base64_decode($payload, true) ?: '';
        }

        if (!str_contains($decoded, '|')) {
            return null;
        }

        [$idPart, $rawToken] = explode('|', $decoded, 2);
        $id = intval($idPart);
        if (!$id || !$rawToken) {
            return null;
        }

        // raw token is "rawId.rawSecret"
        if (!str_contains($rawToken, '.')) {
            return null;
        }
        [$rawId, $rawSecret] = explode('.', $rawToken, 2);

        $device = TrustedDevice::where('id', $id)
            ->where('raw_token_id', $rawId)
            ->where('is_active', true)
            ->first();

        if (!$device) {
            return null;
        }

        // validate secret hash
        $expectedHash = hash_hmac('sha256', $rawSecret, config('app.key'));
        if (!hash_equals($expectedHash, $device->token_hash)) {
            return null;
        }

        // also check expiry
        if ($device->expires_at && Carbon::parse($device->expires_at)->isPast()) {
            return null;
        }

        return $device;
    }

    /**
     * Mark a device last used and optionally rotate token (recommended)
     */
    public function touch(TrustedDevice $device, Request $request, bool $rotate = false): array
    {
        $device->last_used_at = now();
        $device->ip_address = $request->ip();
        $device->user_agent = substr($request->userAgent() ?? '', 0, 1000);
        $device->save();

        if ($rotate) {
            // rotate secret portion but keep id -> so clients must be given new raw token
            $newSecret = Str::random(64);
            $newHash = hash_hmac('sha256', $newSecret, config('app.key'));
            $device->token_hash = $newHash;
            $device->save();
            $rawToken = "{$device->raw_token_id}.{$newSecret}";
            return [$device, $rawToken];
        }

        return [$device, null];
    }

    /**
     * Validate cookie payload and return device if valid
     * This is an alias for findByPayload for backward compatibility
     */
    public function validateCookie(?string $payload, $user = null): ?TrustedDevice
    {
        $device = $this->findByPayload($payload);
        
        // If user is provided, ensure it matches
        if ($device && $user && $device->user_id !== $user->id) {
            return null;
        }
        
        return $device;
    }

    /**
     * Remove (revoke) a trusted device
     */
    public function revoke(TrustedDevice $device): void
    {
        $device->is_active = false;
        $device->save();
    }
}
