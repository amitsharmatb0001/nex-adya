<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;
use App\Services\ActivityLogService;


class AccountController extends Controller
{
    /**
     * PATCH /api/v1/account
     * Update basic user info.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'sometimes|string|max:15',
        ]);

        $user->update($data);

        return response()->json([
            'ok'   => true,
            'data' => $user->only(['id','name','email','phone']),
        ]);
    }

    /**
     * POST /api/v1/account/password
     * Change password securely.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'old_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ]);

        if (!Hash::check($data['old_password'], $user->password)) {
            return response()->json([
                'ok'    => false,
                'error' => ['code' => 'INVALID_OLD_PASSWORD', 'message' => 'Old password is incorrect.'],
            ], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json(['ok' => true, 'message' => 'Password updated successfully.']);
    }

    /**
     * DELETE /api/v1/account
     * Delete account + cascade tokens.
     * For compliance, we soft delete (can purge later).
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        // revoke all tokens
        $user->tokens()->delete();

        // soft delete user (make sure model uses SoftDeletes)
        $user->delete();

        return response()->json(['ok' => true, 'message' => 'Account deleted.']);
    }

    /**
     * GET /api/v1/account/export
     * Export user data (DPDP compliance).
     */
    public function export(Request $request)
    {
        $user = $request->user();

        // collect basic + relations
        $data = [
            'user'       => $user->only(['id','name','email','phone','created_at']),
            'addresses'  => $user->addresses()->get()->toArray(),
            'orders'     => $user->orders()->with('items')->get()->toArray(),
        ];

        Log::info("User {$user->id} requested account export.");

        return response()->json([
            'ok'   => true,
            'data' => $data,
        ]);

        ActivityLogService::log('account_deleted');
    }
}
