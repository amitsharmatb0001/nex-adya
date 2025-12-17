<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum','role:admin']);
    }

    // GET /api/v2/admin/payouts
    public function index()
    {
        return response()->json([
            'ok' => true,
            'data' => Payout::with(['restaurant:id,name','requester:id,name,email'])->paginate(25)
        ]);
    }

    // POST /api/v2/admin/payouts/{id}/approve
    public function approve($id, Request $request)
    {
        $payout = Payout::findOrFail($id);

        $payout->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'notes'       => $request->input('notes'),
        ]);

        return response()->json(['ok' => true, 'data' => $payout]);
    }

    // POST /api/v2/admin/payouts/{id}/reject
    public function reject($id, Request $request)
    {
        $payout = Payout::findOrFail($id);

        $payout->update([
            'status'      => 'rejected',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'notes'       => $request->input('notes'),
        ]);

        return response()->json(['ok' => true, 'data' => $payout]);
    }
}
