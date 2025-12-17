<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\TrustedDeviceService;
use App\Services\ActivityLogService;

class TwoFactorController extends Controller
{
    /**
     * Verify OTP sent after login.
     * Supports optional trust_device -> creates TrustedDevice & sets secure cookie.
     */
    public function verify(Request $request, TrustedDeviceService $trustedService): JsonResponse
    {
        $request->validate([
            'email'        => 'required|email',
            'otp'          => 'required|string|size:6',
            'trust_device' => 'sometimes|boolean',
            'device_name'  => 'sometimes|string|max:255',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $cacheKey = "otp:{$user->id}";
        $cachedOtp = Cache::get($cacheKey);

        Log::debug('2FA verify attempt', [
            'email' => $request->email,
            'inputOtp' => $request->otp,
            'userId' => $user->id,
            'cachedOtpExists' => $cachedOtp !== null,
        ]);

        if (!$cachedOtp || !hash_equals($cachedOtp, $request->otp)) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'INVALID_OTP', 'message' => 'Invalid or expired OTP']
            ], 422);
        }

        // consume OTP
        Cache::forget($cacheKey);

        // issue final auth token
        $token = $user->createToken('auth')->plainTextToken;

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

        ActivityLogService::log('login_success', ['user_id' => $user->id, 'ip' => $request->ip()]);

        $response = response()->json([
            'ok' => true,
            'data' => ['user' => $user->only(['id','name','email']), 'token' => $token],
        ]);

        if (isset($cookieValue)) {
            return $response->withCookie($cookieValue);
        }

        return $response;
    }

    /**
     * Resend OTP if user didn’t receive it.
     */
    public function resend(Request $request): JsonResponse
    {
        Log::debug('Resend endpoint hit', ['email' => $request->input('email')]);

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->firstOrFail();

        $cacheKey = "otp:{$user->id}";

        // Rate limiter: if OTP was issued very recently, block frequent resends
        $lastTsKey = $cacheKey . ':ts';
        $last = Cache::get($lastTsKey);
        if ($last && now()->diffInSeconds($last) < 30) {
            return response()->json(['ok'=>false,'error'=>['code'=>'TOO_MANY_REQUESTS','message'=>'Try again shortly']], 429);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($cacheKey, $otp, now()->addMinutes(5));
        Cache::put($lastTsKey, now(), now()->addMinutes(5));

        Log::info("Resent OTP for user {$user->email}", ['otp' => $otp, 'user_id' => $user->id]);

        if (app()->environment('local','testing')) {
            return response()->json(['ok'=>true,'data'=>['otp'=>$otp],'message'=>'OTP resent (local/testing)']);
        }

        // In production: send via email/SMS and do not return OTP
        // Mail::to($user->email)->send(new TwoFactorMail($otp));
        return response()->json(['ok'=>true,'data'=>['message'=>'OTP resent successfully']]);
    }
}
