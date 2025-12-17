<?php
namespace App\Http\Controllers\Api\V2\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\PayoutRequest;
use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum','role:restaurant']);
    }

    // GET /api/v2/owner/payouts
    public function index(Request $request)
    {
        $owner = $request->user();
        return response()->json([
            'ok' => true,
            'data' => Payout::where('requested_by', $owner->id)
                            ->with('restaurant:id,name')
                            ->latest()
                            ->paginate(20)
        ]);
    }

    // POST /api/v2/owner/payouts
    public function store(PayoutRequest $request)
    {
        $owner = $request->user();

        $payout = Payout::create([
            'restaurant_id' => $request->restaurant_id,
            'requested_by'  => $owner->id,
            'amount'        => $request->amount,
            'status'        => 'pending',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $payout
        ], 201);
    }
}
