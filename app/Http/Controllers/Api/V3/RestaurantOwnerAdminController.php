<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RestaurantOwner;
use App\Services\AdminService;
use Illuminate\Support\Facades\Log;

class RestaurantOwnerAdminController extends Controller
{
    public function index(Request $r) {
        $q = RestaurantOwner::with(['user','restaurant'])->latest();
        $data = $q->paginate(25);
        return response()->json(['ok'=>true,'data'=>$data]);
    }

    public function approve(Request $r, int $id, AdminService $service) {
        $row = RestaurantOwner::findOrFail($id);
        if ($row->is_active) {
            return response()->json(['ok'=>false,'error'=>['message'=>'Already active']], 422);
        }
        $row->is_active = true;
        $row->approved_by = $r->user()->id;
        $row->approved_at = now();
        $row->save();

        // Audit event
        $service->logAdminAction($r->user(), 'restaurant_owner_approved', [
            'owner_id' => $row->id, 'restaurant_id' => $row->restaurant_id
        ]);

        return response()->json(['ok'=>true,'data'=>$row]);
    }

    public function reject(Request $r, int $id, AdminService $service) {
        $row = RestaurantOwner::findOrFail($id);
        $row->is_active = false;
        $row->rejected_by = $r->user()->id;
        $row->rejected_at = now();
        $row->save();

        $service->logAdminAction($r->user(), 'restaurant_owner_rejected', [
            'owner_id' => $row->id, 'restaurant_id' => $row->restaurant_id
        ]);

        return response()->json(['ok'=>true]);
    }
}
