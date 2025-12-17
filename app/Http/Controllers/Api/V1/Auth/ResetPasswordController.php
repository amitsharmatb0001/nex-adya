<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ResetPasswordController extends Controller
{
    /**
     * Handle password reset.
     */
    public function reset(Request $request)
    {
        try {
            $request->validate([
                'token'    => 'required',
                'email'    => 'required|email|exists:users,email',
                'password' => [
                    'required',
                    'string',
                    'min:12',                // high security
                    'regex:/[A-Z]/',         // uppercase
                    'regex:/[a-z]/',         // lowercase
                    'regex:/[0-9]/',         // number
                    'regex:/[\W_]/',         // special character
                ],
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    // revoke old tokens for safety
                    $user->tokens()->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                Log::info("Password reset for {$request->email}");

                return response()->json([
                    'ok' => true,
                    'message' => __($status),
                ]);
            }

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        } catch (\Throwable $e) {
            Log::error("ResetPassword error: " . $e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'RESET_PASSWORD_ERROR',
                    'message' => 'Unable to reset password.',
                ],
            ], 500);
        }
    }
}
