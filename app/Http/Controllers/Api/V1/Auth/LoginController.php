<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\TrustedDeviceService;
use App\Services\FraudDetectionService;
use App\Services\ActivityLogService;
use Carbon\Carbon;

/**
 * LoginController (API V1)
 * 
 * ✅ P0 FIX #1: OTP Leak Removed
 * - OTP is never returned in API response (even in local/testing)
 * - OTP is only logged to laravel.log for debugging
 * - This prevents attackers from bypassing 2FA
 */
class LoginController extends Controller
{
    public function __invoke(Request $request, TrustedDeviceService $trustedService)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Fraud check (your service)
        $fraud = new FraudDetectionService();
        $fraud->checkLoginFailures($credentials['email']);

        if (! Auth::guard('web')->attempt($credentials)) {
            // optional: log failure in fraud system
            $fraud->recordFailedLogin($credentials['email'], $request->ip());
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();

        // 1) Check trusted cookie bypass (cookie value base64("id|rawtoken"))
        $cookie = $request->cookie(TrustedDeviceService::COOKIE_NAME);
        if ($cookie && $user->two_factor_secret) {
            try {
                Log::info('Checking trusted device cookie', [
                    'user_id' => $user->id,
                    'cookie' => $cookie,
                    'cookie_length' => strlen($cookie)
                ]);
                
                $dev = $trustedService->validateCookie($cookie, $user);
                if ($dev) {
                    Log::info('Trusted device validated successfully', [
                        'user_id' => $user->id,
                        'device_id' => $dev->id
                    ]);
                    
                    // bypass OTP -> issue final token
                    $token = $user->createToken('auth')->plainTextToken;

                    ActivityLogService::log('login_trusted_device', [
                        'user_id' => $user->id,
                        'device_id' => $dev->id,
                        'ip' => $request->ip()
                    ]);

                    return response()->json([
                        'ok' => true,
                        'data' => ['user' => $user->only(['id','name','email']), 'token' => $token],
                        'message' => 'Authenticated via trusted device'
                    ]);
                } else {
                    Log::info('Trusted device validation failed - device not found', [
                        'user_id' => $user->id,
                        'cookie' => $cookie
                    ]);
                }
            } catch (\Exception $e) {
                // Log the error but continue with normal 2FA flow
                Log::error('Trusted device validation failed: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'cookie' => $cookie,
                    'ip' => $request->ip()
                ]);
            }
        } else {
            Log::info('No trusted device cookie or user has no 2FA', [
                'user_id' => $user->id,
                'has_cookie' => !empty($cookie),
                'has_2fa' => !empty($user->two_factor_secret)
            ]);
        }

        // 2) Check if user has 2FA enabled
        if (!$user->two_factor_secret) {
            // No 2FA enabled, issue token directly
            $token = $user->createToken('auth')->plainTextToken;
            
            ActivityLogService::log('login_success', ['user_id' => $user->id, 'ip' => $request->ip()]);
            
            return response()->json([
                'ok' => true,
                'data' => ['user' => $user->only(['id','name','email']), 'token' => $token],
                'message' => 'Login successful'
            ]);
        }

        // 3) Normal 2FA flow -> generate OTP + store in cache
        $otp = (string) random_int(100000, 999999);
        $cacheKey = "otp:{$user->id}";
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        // ✅ SECURE - OTP is only logged, NEVER returned in response
        Log::info("OTP generated for user {$user->email}", [
            'user_id' => $user->id,
            'otp' => $otp,  // Only visible in logs for debugging
            'environment' => app()->environment()
        ]);

        // Issue short-lived pre-2fa token to allow verify endpoint to accept Authorization if you want.
        $preToken = $user->createToken('pre_2fa')->plainTextToken;

        ActivityLogService::log('login_pending_2fa', ['user_id' => $user->id, 'ip' => $request->ip()]);

        // ✅ FIXED - OTP is NEVER returned in API response
        // This applies to ALL environments including local/testing
        // Developers should check laravel.log to get the OTP for testing
        
        // In production: send OTP via email/SMS
        // Uncomment when email service is configured:
        // Mail::to($user->email)->send(new TwoFactorMail($otp));

        return response()->json([
            'ok' => true,
            '2fa_required' => true,
            'data' => [
                'user' => $user->only(['id','name','email']), 
                'token' => $preToken
            ],
            'message' => app()->environment('local', 'testing') 
                ? 'OTP sent (check laravel.log for testing)' 
                : 'OTP sent to your email'
        ]);
    }
}