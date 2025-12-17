<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TrustedDeviceService;

class LogoutController extends Controller
{
    public function __invoke(Request $request, TrustedDeviceService $trustedService)
    {
        // revoke current access token
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        // if a trusted-device cookie exists, revoke DB entry and clear cookie
        $cookie = $request->cookie(TrustedDeviceService::COOKIE_NAME);
        if ($cookie) {
            $dev = $trustedService->findByPayload($cookie);
            if ($dev && $dev->user_id === $request->user()->id) {
                $trustedService->revoke($dev);
            }
        }

        // return response clearing the cookie as well
        return response()->json(['ok'=>true,'data'=>['message'=>'Logged out']])
                         ->withoutCookie(TrustedDeviceService::COOKIE_NAME);
    }
}
