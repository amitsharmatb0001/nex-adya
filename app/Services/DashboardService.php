<?php

namespace App\Services;

use App\Models\{Order, OrderItem, MenuItem, RestaurantOwner};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * DashboardService
 *
 * Provides aggregated metrics for a restaurant owner dashboard:
 * - overview (active orders, revenue, today orders)
 * - top-selling items (time range)
 * - recent orders (paginated)
 *
 * All money values are in paise (integer). Convert to display at caller level.
 */
class DashboardService
{
    /**
     * Get owner -> restaurant id mapping.
     * Throws when owner not found (caller should handle)
     */
    public function getRestaurantForOwner(int $userId): int
    {
        $row = RestaurantOwner::where('user_id', $userId)
            ->where('is_active', true)
            ->firstOrFail();

        return (int) $row->restaurant_id;
    }

    /**
     * Overview summary (cached short window).
     *
     * Returns:
     * [
     *   'active_orders' => int,
     *   'pending_confirmed' => int,
     *   'today_orders' => int,
     *   'today_revenue' => int (paise),
     *   'monthly_revenue' => int (paise),
     *   'avg_order_value' => int (paise),
     * ]
     */
    public function overview(int $restaurantId): array
    {
        $cacheKey = "dash:overview:rest:{$restaurantId}";
        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($restaurantId) {

            $now = Carbon::now();
            $todayStart = $now->copy()->startOfDay();
            $monthStart = $now->copy()->startOfMonth();

            // Active orders: pending/confirmed/preparing/out_for_delivery
            $activeOrders = Order::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['pending','confirmed','preparing','out_for_delivery'])
                ->count();

            // pending + confirmed quick split (useful for UI)
            $pendingConfirmed = Order::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['pending','confirmed'])
                ->count();

            // today's orders and revenue
            $todayOrdersQ = Order::where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', $todayStart);

            $todayOrders = $todayOrdersQ->count();
            $todayRevenue = (int) $todayOrdersQ->sum('total');

            // monthly revenue
            $monthlyRevenue = (int) Order::where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', $monthStart)
                ->sum('total');

            // avg order value (last 30 days)
            $avgOrderValue = (int) Order::where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->avg('total') ?? 0;

            return [
                'active_orders'     => $activeOrders,
                'pending_confirmed' => $pendingConfirmed,
                'today_orders'      => $todayOrders,
                'today_revenue'     => $todayRevenue,
                'monthly_revenue'   => $monthlyRevenue,
                'avg_order_value'   => (int) $avgOrderValue,
            ];
        });
    }

    /**
     * Top-selling items for a restaurant over the given days window.
     *
     * returns collection of:
     * [
     *   menu_item_id, name, sold_qty, revenue (paise), image_url
     * ]
     */
    public function topItems(int $restaurantId, int $days = 30, int $limit = 10): Collection
    {
        $since = Carbon::now()->subDays($days);

        // Join order_items -> menu_items -> menus to ensure items belong to restaurant
        return Cache::remember("dash:topitems:rest:{$restaurantId}:{$days}:{$limit}", now()->addMinutes(2), function () use ($restaurantId, $since, $limit) {

            $rows = DB::table('order_items')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->join('menus', 'menu_items.menu_id', '=', 'menus.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('menus.restaurant_id', $restaurantId)
                ->where('orders.created_at', '>=', $since)
                ->select(
                    'menu_items.id as menu_item_id',
                    'menu_items.name as name',
                    'menu_items.image_url as image_url',
                    DB::raw('SUM(order_items.quantity) as sold_qty'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
                )
                ->groupBy('menu_items.id','menu_items.name','menu_items.image_url')
                ->orderByDesc('sold_qty')
                ->limit($limit)
                ->get();

            return $rows->map(fn($r) => (array)$r);
        });
    }

    /**
     * Recent orders list for a restaurant (paginated style).
     * Returns Laravel paginator-like array (caller may adapt).
     */
    public function recentOrders(int $restaurantId, int $page = 1, int $size = 20)
    {
        $query = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->with([
                'items' => fn($q) => $q->select('id','order_id','menu_item_id','quantity','price'),
                'user:id,name,email'
            ])
            ->orderByDesc('id');

        // simple pagination
        $p = $query->paginate($size, ['*'], 'page', $page);
        return $p;
    }

    /**
     * Revenue trend (daily totals) for the last N days.
     * Returns array of buckets ['date' => 'YYYY-MM-DD', 'revenue' => paise]
     */
    public function revenueTrend(int $restaurantId, int $days = 14): array
    {
        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        // group by date
        $rows = DB::table('orders')
            ->select(DB::raw("DATE(created_at) as day"), DB::raw("SUM(total) as revenue"))
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $out[] = [
                'date' => $key,
                'revenue' => isset($rows[$key]) ? (int)$rows[$key]->revenue : 0,
            ];
        }

        return $out;
    }
}
