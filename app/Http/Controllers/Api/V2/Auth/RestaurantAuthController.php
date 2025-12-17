<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\RegisterOwnerRequest;
use App\Http\Requests\Api\V2\Auth\LoginOwnerRequest;
use App\Models\User;
use App\Models\RestaurantOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RestaurantAuthController
 *
 * Notes:
 * - register() now creates a pending RestaurantOwner (is_active = false).
 * - Admin must approve later via RestaurantOwnerController@approve.
 * - login() denies access unless owner row exists and is_active = true.
 */
class RestaurantAuthController extends Controller
{
    /**
     * Register a restaurant owner and link to a restaurant.
     * Owner is created in PENDING state, requires admin approval.
     *
     * POST /api/v2/auth/register
     */
    public function register(RegisterOwnerRequest $req)
    {
        $payload = $req->validated();

        return DB::transaction(function () use ($payload) {
            $user = User::create([
                'name'     => $payload['name'],
                'email'    => $payload['email'],
                'password' => Hash::make($payload['password']),
                'role'     => 'restaurant',
            ]);

            // Create pending restaurant owner record (admin must approve)
            RestaurantOwner::create([
                'user_id'       => $user->id,
                'restaurant_id' => (int) $payload['restaurant_id'],
                'role'          => 'owner',
                'is_active'     => false,   // pending
            ]);

            return response()->json([
                'ok' => true,
                'data' => [
                    'user'   => $user->only(['id','name','email','role']),
                    'status' => 'pending_approval',
                ],
                'message' => 'Account created. Pending admin approval.',
            ], 201);
        });
    }

    /**
     * Login for restaurant owners
     *
     * POST /api/v2/auth/login
     */
    public function login(Request $req)
    {
        $payload = $req->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $payload['email'])->first();

        if (!$user || !Hash::check($payload['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if (($user->role ?? '') !== 'restaurant') {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'NOT_RESTAURANT', 'message' => 'This account is not a restaurant owner.']
            ], 403);
        }

        // ensure linked RestaurantOwner exists and is active
        $owner = RestaurantOwner::where('user_id', $user->id)->first();
        if (!$owner || !$owner->is_active) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'OWNER_NOT_ACTIVE', 'message' => 'Account pending approval or inactive.']
            ], 403);
        }

        $token = $user->createToken('restaurant')->plainTextToken;

        return response()->json([
            'ok' => true,
            'data' => [
                'user'  => $user->only(['id','name','email','role']),
                'token' => $token
            ],
        ]);
    }

    /**
     * Small helper to fetch current owner info (auth:sanctum + role:restaurant)
     * GET /api/v2/auth/me
     */
    public function me(Request $req)
    {
        $user = $req->user();
        $owner = RestaurantOwner::where('user_id', $user->id)->with('restaurant')->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'user'  => $user->only(['id','name','email','role']),
                'owner' => $owner,
            ],
        ]);
    }
}
