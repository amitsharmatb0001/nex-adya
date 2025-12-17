<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payout;
use App\Services\PayoutService;
use App\Http\Requests\PayoutApproveRequest;
use App\Services\AdminService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Events\PayoutStatusChanged;
use App\Jobs\ProcessPayoutJob;

class PayoutAdminController extends Controller
{
    /**
     * GET /api/v3/payouts
     * Admin: list payouts (paginated + filters)
     */
    public function index(Request $req)
    {
        $q = Payout::with(['restaurant','requester','approver'])->orderByDesc('id');

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }
        if ($rid = $req->query('restaurant_id')) {
            $q->where('restaurant_id', $rid);
        }

        $data = $q->paginate(25)->appends($req->query());
        return response()->json(['ok'=>true,'data'=>$data]);
    }

    /**
     * POST /api/v3/payouts/{id}/approve
     */
    public function approve(Request $req, int $id)
    {
        $admin = $req->user();

        return DB::transaction(function () use ($admin, $id) {
            $payout = Payout::lockForUpdate()->findOrFail($id);
            $oldStatus = $payout->status;

            if ($payout->status !== 'pending') {
                return response()->json(['ok'=>false,'error'=>['message'=>'Only pending payouts can be approved']], 422);
            }

            $payout->update([
                'status' => 'processing',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            Log::info("Payout approved by admin", ['payout_id' => $payout->id, 'admin_id' => $admin->id]);

            // Dispatch event
            event(new PayoutStatusChanged($payout, $oldStatus, 'approved'));
            
            // Dispatch job to process payout
            dispatch(new ProcessPayoutJob($payout->id));

            // Activity log
            \App\Services\ActivityLogService::log('payout_approved', [
                'payout_id' => $payout->id,
                'approved_by' => $admin->id
            ]);

            return response()->json(['ok'=>true,'data'=>$payout]);
        });
    }

    /**
     * POST /api/v3/payouts/{id}/reject
     */
    public function reject(Request $req, int $id)
    {
        $admin = $req->user();
        $payload = $req->validate(['reason' => 'required|string|max:2000']);

        $payout = Payout::findOrFail($id);
        $oldStatus = $payout->status;
        
        if ($payout->status !== 'pending') {
            return response()->json(['ok'=>false,'error'=>['message'=>'Only pending payouts can be rejected']], 422);
        }

        $payout->update([
            'status' => 'rejected',
            'rejected_by' => $admin->id,
            'rejected_at' => now(),
            'notes' => ($payout->notes ?? '') . "\n\n[Rejected by admin {$admin->id}] " . $payload['reason']
        ]);

        // Dispatch event
        event(new PayoutStatusChanged($payout, $oldStatus, 'rejected'));

        \App\Services\ActivityLogService::log('payout_rejected', [
            'payout_id' => $payout->id,
            'rejected_by' => $admin->id,
            'reason' => $payload['reason']
        ]);

        return response()->json(['ok'=>true,'data'=>$payout]);
    }

    /**
     * POST /api/v3/payouts/{id}/fail
     */
    public function fail(Request $req, int $id)
    {
        $admin = $req->user();
        $payload = $req->validate(['reason' => 'required|string|max:2000']);

        $payout = Payout::findOrFail($id);
        $oldStatus = $payout->status;
        
        if (! in_array($payout->status, ['approved','processing'])) {
            return response()->json(['ok'=>false,'error'=>['message'=>'Only approved/processing payouts can be marked failed']], 422);
        }

        $payout->update([
            'status' => 'failed',
            'notes' => ($payout->notes ?? '') . "\n\n[Failed by admin {$admin->id}] " . $payload['reason']
        ]);

        // Dispatch event
        event(new PayoutStatusChanged($payout, $oldStatus, 'failed'));

        \App\Services\ActivityLogService::log('payout_failed_manual', [
            'payout_id' => $payout->id,
            'failed_by' => $admin->id,
            'reason' => $payload['reason']
        ]);

        return response()->json(['ok'=>true,'data'=>$payout]);
    }

    /**
     * GET /api/v3/payouts/export
     * Export CSV (streamed)
     */
    public function export(Request $req): StreamedResponse
    {
        $q = Payout::query()->with('restaurant','requester')->orderByDesc('id');

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }

        $filename = 'payouts_export_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($q) {
            $handle = fopen('php://output', 'w');
            // header row
            fputcsv($handle, ['id','restaurant_id','restaurant','requested_by','amount_in_paise','status','approved_by','approved_at','paid_at','notes']);

            $q->chunk(200, function($rows) use ($handle) {
                foreach ($rows as $r) {
                    fputcsv($handle, [
                        $r->id,
                        $r->restaurant_id,
                        $r->restaurant?->name,
                        $r->requested_by,
                        $r->amount,
                        $r->status,
                        $r->approved_by,
                        $r->approved_at?->toIso8601String(),
                        $r->paid_at?->toIso8601String(),
                        $r->notes,
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }
}
