<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
    /**
     * Send reset link email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                Log::info("Password reset link sent to {$request->email}");

                return response()->json([
                    'ok' => true,
                    'message' => __($status),
                ]);
            }

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        } catch (\Throwable $e) {
            Log::error("ForgotPassword error: " . $e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'FORGOT_PASSWORD_ERROR',
                    'message' => 'Unable to send reset link at the moment.',
                ],
            ], 500);
        }
    }
}
