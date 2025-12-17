<?php

namespace App\Http\Controllers\Api\V2\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Log;

/**
 * Owner Dashboard Controller (v2)
 *
 * All routes must be protected with 'auth:sanctum' and 'role:restaurant' middleware
 * in routes file (RouteServiceProvider already mounts api/v2).
 *
 * Endpoints:
 * GET  /api/v2/dashboard        -> overview + quick stats
 * GET  /api/v2/dashboard/top-items -> top items (days, limit)
 * GET  /api/v2/dashboard/orders -> recent orders (page, size)
 * GET  /api/v2/dashboard/trend  -> revenue trend (days)
 */
class DashboardController extends Controller
{
    public function __construct()
    {
        // Expect middleware to be applied in routes or here
        $this->middleware(['auth:sanctum', 'role:restaurant']);
    }

    public function index(Request $request, DashboardService $svc)
    {
        $userId = $request->user()->id;
        $restaurantId = $svc->getRestaurantForOwner($userId);

        $overview = $svc->overview($restaurantId);

        return response()->json(['ok' => true, 'data' => [
            'restaurant_id' => $restaurantId,
            'overview' => $overview,
        ]]);
    }

    public function topItems(Request $request, DashboardService $svc)
    {
        $userId = $request->user()->id;
        $restaurantId = $svc->getRestaurantForOwner($userId);

        $days = (int) $request->query('days', 30);
        $limit = (int) $request->query('limit', 10);

        $rows = $svc->topItems($restaurantId, $days, $limit);

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function orders(Request $request, DashboardService $svc)
    {
        $userId = $request->user()->id;
        $restaurantId = $svc->getRestaurantForOwner($userId);

        $page = max(1, (int)$request->query('page', 1));
        $size = min(50, max(5, (int)$request->query('size', 20)));

        $p = $svc->recentOrders($restaurantId, $page, $size);

        return response()->json(['ok' => true, 'data' => $p]);
    }

    public function trend(Request $request, DashboardService $svc)
    {
        $userId = $request->user()->id;
        $restaurantId = $svc->getRestaurantForOwner($userId);

        $days = (int) $request->query('days', 14);
        $rows = $svc->revenueTrend($restaurantId, $days);

        return response()->json(['ok' => true, 'data' => $rows]);
    }
}
