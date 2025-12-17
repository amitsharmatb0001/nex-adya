<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryPartner;
use App\Services\DeliveryService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    protected DeliveryService $service;

    public function __construct(DeliveryService $service)
    {
        // Ensure token-level auth (sanctum). Additional 'role:delivery' middleware recommended.
        $this->middleware('auth:sanctum');
        $this->middleware('role:delivery')->only(['accept','pickup','deliver']); // optional if you have role middleware
        $this->service = $service;
    }

    /**
     * POST /api/v4/deliveries/{id}/accept
     * Partner accepts an order assignment.
     */
    public function accept(Request $request, int $id)
    {
        $partner = DeliveryPartner::where('user_id', $request->user()->id)->firstOrFail();
        $order   = Order::findOrFail($id);
        
        // Use proper authorization instead of manual checks
        $this->authorize('actOnOrder', $order);

        $oldStatus = $order->status;
        $updated = $this->service->updateStatus($order, $partner, 'accept');

        // Log the delivery action
        ActivityLogService::log('delivery_action', [
            'user_id' => $partner->user_id,
            'partner_id' => $partner->id,
            'order_id' => $order->id,
            'action' => 'accept',
            'from' => $oldStatus,
            'to' => $updated->status,
            'ip' => request()->ip(),
        ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'order_id' => $updated->id,
                'status'   => $updated->status,
            ],
        ]);
    }

    /**
     * POST /api/v4/deliveries/{id}/pickup
     * Partner confirms they picked up the order.
     */
    public function pickup(Request $request, int $id)
    {
        $partner = DeliveryPartner::where('user_id', $request->user()->id)->firstOrFail();
        $order   = Order::findOrFail($id);
        
        // Use proper authorization instead of manual checks
        $this->authorize('actOnOrder', $order);

        $oldStatus = $order->status;
        $updated = $this->service->updateStatus($order, $partner, 'pickup');

        // Log the delivery action
        ActivityLogService::log('delivery_action', [
            'user_id' => $partner->user_id,
            'partner_id' => $partner->id,
            'order_id' => $order->id,
            'action' => 'pickup',
            'from' => $oldStatus,
            'to' => $updated->status,
            'ip' => request()->ip(),
        ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'order_id' => $updated->id,
                'status'   => $updated->status,
                'picked_up_at' => $updated->picked_up_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v4/deliveries/{id}/deliver
     * Partner confirms the order has been delivered.
     */
    public function deliver(Request $request, int $id)
    {
        $partner = DeliveryPartner::where('user_id', $request->user()->id)->firstOrFail();
        $order   = Order::findOrFail($id);
        
        // Use proper authorization instead of manual checks
        $this->authorize('actOnOrder', $order);

        $oldStatus = $order->status;
        $updated = $this->service->updateStatus($order, $partner, 'deliver');

        // Log the delivery action
        ActivityLogService::log('delivery_action', [
            'user_id' => $partner->user_id,
            'partner_id' => $partner->id,
            'order_id' => $order->id,
            'action' => 'deliver',
            'from' => $oldStatus,
            'to' => $updated->status,
            'ip' => request()->ip(),
        ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'order_id' => $updated->id,
                'status'   => $updated->status,
                'delivered_at' => $updated->delivered_at?->toIso8601String(),
            ],
        ]);
    }
}
