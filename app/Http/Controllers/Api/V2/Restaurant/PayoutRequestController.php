<?php

namespace App\Http\Controllers\Api\V2\Restaurant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payout;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayoutRequestController extends Controller
{
    /**
     * POST /api/v2/payouts/request
     * Body: { restaurant_id, amount, notes, external_reference (optional) }
     */
    public function store(Request $request)
    {
        $payload = $request->validate([
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'amount' => 'required|integer|min:100', // paise min 1.00
            'notes'  => 'nullable|string|max:2000',
            'external_reference' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // Authorization: ensure user is assigned as owner for this restaurant
        $owner = \App\Models\RestaurantOwner::where('user_id', $user->id)
            ->where('restaurant_id', $payload['restaurant_id'])
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            return response()->json(['ok'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'You are not an active owner of this restaurant']], 403);
        }

        $payout = Payout::create([
            'restaurant_id' => $payload['restaurant_id'],
            'requested_by' => $user->id,
            'external_reference' => $payload['external_reference'] ?? Str::uuid(),
            'amount' => $payload['amount'],
            'currency' => 'INR',
            'status' => 'pending',
            'notes' => $payload['notes'] ?? null,
        ]);

        Log::info("Payout requested", ['payout_id' => $payout->id, 'restaurant_id' => $payout->restaurant_id, 'by' => $user->id]);

        // Activity logging (optional)
        \App\Services\ActivityLogService::log('payout_requested', [
            'payout_id' => $payout->id,
            'restaurant_id' => $payout->restaurant_id,
            'user_id' => $user->id
        ]);

        return response()->json(['ok' => true, 'data' => $payout], 201);
    }

    /**
     * GET /api/v2/payouts
     * List payouts for this restaurant (owner)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $restaurantId = $request->query('restaurant_id');

        $owner = \App\Models\RestaurantOwner::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            return response()->json(['ok'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'You are not an active owner of this restaurant']], 403);
        }

        $q = Payout::where('restaurant_id', $restaurantId)->orderByDesc('id');

        $data = $q->paginate(20);
        return response()->json(['ok'=>true,'data'=>$data]);
    }
}
