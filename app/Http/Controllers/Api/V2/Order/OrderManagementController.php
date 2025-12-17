<?php

namespace App\Http\Controllers\Api\V2\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Order;
use App\Models\RestaurantOwner;
use App\Services\OrderService;

/**
 * OrderManagementController
 *
 * Restaurant / Admin side order management endpoints (API v2).
 *
 * Routes expected:
 *   GET  /api/v2/orders                 -> index()
 *   GET  /api/v2/orders/{id}            -> show($id)
 *   PATCH /api/v2/orders/{id}/status    -> updateStatus($request, $id)
 *
 * Important:
 *  - Caller must be authenticated (auth:sanctum)
 *  - Caller must be a restaurant role (role:restaurant) for owner actions
 *  - Admin access is allowed by middleware in routes (role:admin)
 *
 * This controller is intentionally thin: business rules, locking,
 * event dispatching and transition validation live in OrderService.
 */
class OrderManagementController extends Controller
{
    public function __construct()
    {
        // Protect endpoints: auth required. Role checks are in routes/middleware.
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/v2/orders
     *
     * List orders for the restaurant owned/managed by the current user.
     * Admins (role:admin) can optionally pass ?restaurant_id= to view specific restaurants.
     *
     * Query params:
     *  - restaurant_id (optional, admin only)
     *  - scope (optional): active | history | all
     *  - page, size
     */
    public function index(Request $request, OrderService $orderService)
    {
        $user = $request->user();

        // Admin can query any restaurant via ?restaurant_id=
        $restaurantId = $request->query('restaurant_id');

        // For non-admins, resolve restaurant via restaurant_owners table
        if (! $user->isAdmin()) {
            $owner = RestaurantOwner::where('user_id', $user->id)->first();
            if (! $owner) {
                return response()->json(['ok' => false, 'error' => ['code' => 'NOT_OWNER', 'message' => 'You are not assigned to any restaurant.']], 403);
            }
            $restaurantId = $owner->restaurant_id;
        }

        // Basic validation for restaurant_id
        if (! $restaurantId) {
            return response()->json(['ok' => false, 'error' => ['code' => 'RESTAURANT_REQUIRED', 'message' => 'restaurant_id is required (or pass as admin).']], 422);
        }

        $scope = $request->query('scope', 'active');
        $size  = (int) $request->query('size', 20);
        $size  = max(1, min(50, $size));

        // Query orders for the restaurant (service helper or direct)
        $query = Order::query()
            ->where('restaurant_id', (int) $restaurantId)
            ->with([
                'user:id,name,email',
                'items' => fn($q) => $q->select('id','order_id','menu_item_id','quantity','price'),
            ])
            ->orderByDesc('id');

        if ($scope === 'active') {
            $query->whereIn('status', ['pending','confirmed','preparing','out_for_delivery']);
        } elseif ($scope === 'history') {
            $query->whereIn('status', ['delivered','cancelled','refunded']);
        } // else 'all'

        $paginator = $query->paginate($size);

        return response()->json(['ok' => true, 'data' => $paginator]);
    }

    /**
     * GET /api/v2/orders/{id}
     *
     * Show single order details for a restaurant owner / admin.
     * Enforces: order.restaurant_id === owner's restaurant_id (for non-admins).
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $order = Order::with([
            'user:id,name,email',
            'address',
            'restaurant:id,name,slug,city,image,eta_minutes',
            'items.menuItem'
        ])->findOrFail($id);

        // Ownership check for non-admins
        if (! $user->isAdmin()) {
            $owner = RestaurantOwner::where('user_id', $user->id)->first();
            if (! $owner || (int)$order->restaurant_id !== (int)$owner->restaurant_id) {
                return response()->json(['ok' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'You are not allowed to view this order.']], 403);
            }
        }

        return response()->json(['ok' => true, 'data' => $order]);
    }

    /**
     * PATCH /api/v2/orders/{id}/status
     *
     * Restaurant (or admin) updates order status.
     * Delegates transition check & event dispatch to OrderService::updateStatus().
     *
     * Input:
     *  - status: required string (allowed statuses validated in service)
     *
     * Response: { ok: true, data: <order> } or { ok:false, error: { ... } }
     */
    public function updateStatus(Request $request, int $id, OrderService $orders)
    {
        $user = $request->user();

        // Validate input status
        $data = $request->validate([
            'status' => ['required','string', Rule::in([
                'pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled','refunded'
            ])],
            'note' => ['nullable','string','max:500'], // optional admin/rest clarifying note
        ]);

        // Resolve order and owner
        $order = Order::findOrFail($id);

        // Non-admins must be owner of the restaurant for this order
        if (! $user->isAdmin()) {
            $owner = RestaurantOwner::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (! $owner) {
                return response()->json(['ok' => false, 'error' => ['code' => 'NOT_OWNER', 'message' => 'You are not assigned/active as restaurant owner.']], 403);
            }

            if ((int)$order->restaurant_id !== (int)$owner->restaurant_id) {
                return response()->json(['ok' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'This order does not belong to your restaurant.']], 403);
            }
        }

        // Delegate to service which handles locking, transition rules, events, invoice dispatch, fraud hooks.
        try {
            $result = $orders->updateStatus($order, $data['status']);

            // If service returns array success pattern (legacy), support that shape:
            if (is_array($result) && array_key_exists('success', $result)) {
                if (! $result['success']) {
                    return response()->json(['ok' => false, 'error' => ['message' => $result['message'] ?? 'Transition not allowed']], 422);
                }
                return response()->json(['ok' => true, 'data' => $result['data']]);
            }

            // If service returns Eloquent Order instance (newer), return it.
            if ($result instanceof Order) {
                return response()->json(['ok' => true, 'data' => $result]);
            }

            // Otherwise, be defensive and return success with result payload
            return response()->json(['ok' => true, 'data' => $result]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('OrderManagementController.updateStatus: order not found', ['order_id' => $id, 'user_id' => $user->id]);
            return response()->json(['ok' => false, 'error' => ['message' => 'Order not found']], 404);

        } catch (\Throwable $e) {
            // Log full error for debugging in dev
            Log::error('OrderManagementController.updateStatus: unexpected error', [
                'order_id' => $id,
                'user_id'  => $user->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => ['message' => 'Could not update order status']], 500);
        }
    }
}
