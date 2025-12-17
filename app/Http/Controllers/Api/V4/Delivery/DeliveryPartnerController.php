<?php

namespace App\Http\Controllers\Api\V4\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

class DeliveryPartnerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:delivery');
    }

    /**
     * GET /api/v4/delivery/profile
     * Get delivery partner's own profile
     */
    public function profile()
    {
        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        // Use proper authorization
        $this->authorize('view', $deliveryPartner);

        $deliveryPartner->load('user');
        
        return response()->json(['ok' => true, 'data' => $deliveryPartner]);
    }

    /**
     * PUT /api/v4/delivery/profile
     * Update delivery partner's own profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        // Use proper authorization
        $this->authorize('update', $deliveryPartner);

        $request->validate([
            'vehicle_type' => 'sometimes|in:bike,car,scooter',
            'vehicle_number' => 'sometimes|string',
            'phone' => 'sometimes|string|unique:delivery_partners,phone,' . $deliveryPartner->id,
            'documents' => 'sometimes|array',
        ]);

        $deliveryPartner->update($request->all());

        // Log the profile update
        ActivityLogService::log('delivery_profile_update', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'updated_fields' => array_keys($request->all()),
            'ip' => request()->ip(),
        ]);

        return response()->json(['ok' => true, 'data' => $deliveryPartner->load('user')]);
    }

    /**
     * POST /api/v4/delivery/availability
     * Update availability status
     */
    public function updateAvailability(Request $request)
    {
        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        // Use proper authorization
        $this->authorize('updateAvailability', $deliveryPartner);

        $oldAvailability = $deliveryPartner->is_available;
        $deliveryPartner->setAvailable($request->boolean('is_available'));

        // Log the availability change
        ActivityLogService::log('delivery_availability_change', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'from' => $oldAvailability,
            'to' => $deliveryPartner->is_available,
            'ip' => request()->ip(),
        ]);

        return response()->json(['ok' => true, 'data' => $deliveryPartner]);
    }

    /**
     * POST /api/v4/delivery/location
     * Update current location
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        // Use proper authorization
        $this->authorize('updateLocation', $deliveryPartner);

        $oldLocation = $deliveryPartner->current_location;
        $deliveryPartner->updateLocation($request->lat, $request->lng);

        // Log the location update
        ActivityLogService::log('delivery_location_update', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'from' => $oldLocation,
            'to' => ['lat' => $request->lat, 'lng' => $request->lng],
            'ip' => request()->ip(),
        ]);

        return response()->json(['ok' => true, 'data' => $deliveryPartner]);
    }

    /**
     * GET /api/v4/delivery/orders
     * Get assigned orders
     */
    public function orders(Request $request)
    {
        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        $query = Order::where('delivery_partner_id', $deliveryPartner->id)
            ->with(['user:id,name,phone', 'restaurant:id,name,address'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->paginate(20)->appends($request->query());
        
        // Log the orders view
        ActivityLogService::log('delivery_orders_view', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'filters' => $request->query(),
            'ip' => request()->ip(),
        ]);
        
        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/v4/delivery/orders/{id}
     * Get specific order details
     */
    public function showOrder(Order $order)
    {
        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner || $order->delivery_partner_id !== $deliveryPartner->id) {
            return response()->json(['ok' => false, 'error' => 'Order not found or not assigned to you'], 404);
        }

        // Use proper authorization
        $this->authorize('actOnOrder', $order);

        $order->load([
            'user:id,name,phone,email',
            'restaurant:id,name,address,phone',
            'items.menuItem:id,name,price',
            'address'
        ]);

        // Log the order view
        ActivityLogService::log('delivery_order_view', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'order_id' => $order->id,
            'ip' => request()->ip(),
        ]);

        return response()->json(['ok' => true, 'data' => $order]);
    }

    /**
     * PATCH /api/v4/delivery/orders/{id}/status
     * Update order status (pickup, delivered, etc.)
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:picked_up,out_for_delivery,delivered',
        ]);

        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner || $order->delivery_partner_id !== $deliveryPartner->id) {
            return response()->json(['ok' => false, 'error' => 'Order not found or not assigned to you'], 404);
        }

        // Use proper authorization
        $this->authorize('actOnOrder', $order);

        $oldStatus = $order->status;
        $order->update(['status' => $request->status]);

        // Update delivery partner stats
        if ($request->status === 'delivered') {
            $deliveryPartner->incrementDeliveries();
        }

        // Log the order status change
        ActivityLogService::log('delivery_order_status_change', [
            'user_id' => $user->id,
            'partner_id' => $deliveryPartner->id,
            'order_id' => $order->id,
            'action' => 'status_update',
            'from' => $oldStatus,
            'to' => $request->status,
            'ip' => request()->ip(),
        ]);

        return response()->json(['ok' => true, 'data' => $order]);
    }

    /**
     * GET /api/v4/delivery/stats
     * Get delivery statistics
     */
    public function stats()
    {
        $user = Auth::user();
        $deliveryPartner = $user->deliveryPartner;
        
        if (!$deliveryPartner) {
            return response()->json(['ok' => false, 'error' => 'Delivery partner profile not found'], 404);
        }

        $stats = [
            'total_deliveries' => $deliveryPartner->total_deliveries,
            'rating' => $deliveryPartner->rating,
            'is_available' => $deliveryPartner->is_available,
            'is_active' => $deliveryPartner->is_active,
            'last_active_at' => $deliveryPartner->last_active_at,
            'current_month_deliveries' => Order::where('delivery_partner_id', $deliveryPartner->id)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'today_deliveries' => Order::where('delivery_partner_id', $deliveryPartner->id)
                ->whereDate('created_at', today())
                ->count(),
        ];

        return response()->json(['ok' => true, 'data' => $stats]);
    }
}
