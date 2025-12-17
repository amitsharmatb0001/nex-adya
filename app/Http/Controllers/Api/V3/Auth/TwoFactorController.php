<?php

namespace App\Http\Controllers\Api\V3\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use App\Services\TrustedDeviceService;
use App\Services\ActivityLogService;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Enable two-factor authentication
     */
    public function enable(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if ($user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled'
            ], 400);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();
        
        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Store secret temporarily (not saved to database yet)
        session(['temp_2fa_secret' => $secret]);

        return response()->json([
            'message' => 'Two-factor authentication setup initiated',
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'requires_verification' => true
        ]);
    }

    /**
     * Verify and complete two-factor authentication setup
     */
    public function verify(Request $request, TrustedDeviceService $trustedService): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'trust_device' => 'sometimes|boolean',
            'device_name' => 'sometimes|string|max:255',
        ]);

        $user = Auth::user();
        $tempSecret = session('temp_2fa_secret');

        if (!$tempSecret) {
            return response()->json([
                'message' => 'No pending two-factor authentication setup'
            ], 400);
        }

        // Verify the code
        if (!$this->google2fa->verifyKey($tempSecret, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.']
            ]);
        }

        // Save the secret to user
        $user->update([
            'two_factor_secret' => Hash::make($tempSecret)
        ]);

        // Clear temporary secret
        session()->forget('temp_2fa_secret');

        // if user requested "trust device" -> create DB record + set cookie
        $cookieValue = null;
        if ($request->boolean('trust_device')) {
            [$devId, $rawToken, $expiresAt] = $trustedService->createForUser(
                $user,
                $request,
                $request->input('device_name') ?? null,
                365 // days validity; adjust as needed
            );

            // cookie payload is base64("id|token")
            $payload = base64_encode($devId . '|' . $rawToken);

            // set secure cookie: httpOnly, secure when using https, samesite strict
            $minutes = now()->diffInMinutes($expiresAt);
            $cookie = cookie(
                TrustedDeviceService::COOKIE_NAME,
                $payload,
                $minutes,
                '/', // path
                null, // domain default
                config('app.env') !== 'local', // secure only on non-local
                true, // httpOnly
                false, // raw
                'Strict' // sameSite
            );

            ActivityLogService::log('trusted_device_created', [
                'user_id' => $user->id, 'device_id' => $devId, 'ip' => $request->ip()
            ]);

            $cookieValue = $cookie;
        }

        ActivityLogService::log('2fa_enabled', [
            'user_id' => $user->id, 'ip' => $request->ip()
        ]);

        $response = response()->json([
            'message' => 'Two-factor authentication enabled successfully'
        ]);

        if (isset($cookieValue)) {
            return $response->withCookie($cookieValue);
        }

        return $response;
    }

    /**
     * Disable two-factor authentication
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = Auth::user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], 400);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.']
            ]);
        }

        // Remove two-factor secret
        $user->update([
            'two_factor_secret' => null
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully'
        ]);
    }

    /**
     * Get two-factor authentication status
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'enabled' => !is_null($user->two_factor_secret),
            'has_pending_setup' => session()->has('temp_2fa_secret')
        ]);
    }

    /**
     * Generate backup codes (if needed)
     */
    public function generateBackupCodes(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], 400);
        }

        // Generate backup codes (8-digit codes)
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        // Hash and store backup codes
        $hashedCodes = array_map(function ($code) {
            return Hash::make($code);
        }, $backupCodes);

        $user->update([
            'two_factor_backup_codes' => json_encode($hashedCodes)
        ]);

        return response()->json([
            'message' => 'Backup codes generated successfully',
            'backup_codes' => $backupCodes,
            'warning' => 'Store these codes securely. They will not be shown again.'
        ]);
    }
}
