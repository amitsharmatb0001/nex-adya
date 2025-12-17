<?php

namespace App\Http\Controllers\Api\V1\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRestaurantsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Restaurant;

/**
 * User-facing restaurant endpoints (v1).
 * List: city-scoped, filtered/sorted, short-cached.
 * Show: includes menus + available items (capped).
 */
class RestaurantController extends Controller
{
    /**
     * GET /api/v1/restaurants
     * Query params:
     * - city (required): string
     * - q             : string (search by name)
     * - cuisine       : string or array (e.g. cuisine=Italian or cuisine[]=Indian&cuisine[]=Thai)
     * - quick_in      : int (ETA filter, e.g. 15/20/25/30/35/40)
     * - min_rating    : float (NEW: filters by minimum restaurant rating)
     * - sort          : trending|eta|new
     * - size          : page size (default 20)
     * - page          : page number
     */
    public function index(ListRestaurantsRequest $r)
    {
        $city     = $r->string('city');
        $q        = $r->string('q');
        $cuisine  = $r->input('cuisine');
        $quickIn  = $r->integer('quick_in');
        $sort     = $r->string('sort', 'trending');
        $size     = (int) ($r->input('size') ?? 20);
        $page     = (int) ($r->input('page') ?? 1);
        $minRating  = $r->input('min_rating');

        // Short cache window; adjust as needed
        $ckey = 'rest:list:' . sha1(json_encode([
            'city'=>$city,'q'=>$q,'cuisine'=>$cuisine,'quick'=>$quickIn,'sort'=>$sort,
            'size'=>$size,'page'=>$page,'min_rating'=>$minRating
        ]));

        return Cache::remember($ckey, now()->addSeconds(30), function () use ($city, $q, $cuisine, $quickIn, $sort, $size, $minRating) {
            $qb = Restaurant::query()
                ->where('city', $city)
                ->where('is_active', true);

            if ($q) {
                $qb->where('name', 'like', "%{$q}%");
            }

            if (!empty($cuisine)) {
                if (is_array($cuisine)) {
                    $qb->whereIn('cuisine', $cuisine);
                } else {
                    $qb->where('cuisine', $cuisine);
                }
            }

            if ($quickIn) {
                $qb->where('eta_minutes', '<=', $quickIn);
            }

            if ($minRating) {
                $qb->where('rating', '>=', $minRating);
            }

            // Flexible sorting
            switch ($sort) {
                case 'eta':
                    $qb->orderBy('eta_minutes')->orderByDesc('rating');
                    break;
                case 'new':
                    $qb->orderByDesc('id');
                    break;
                case 'trending':
                default:
                    $qb->orderByDesc('rating')->orderBy('eta_minutes');
            }

            $data = $qb->paginate($size);

            return response()->json([
                'ok'   => true,
                'meta' => [
                    'city'      => $city,
                    'sort'      => $sort,
                    'quick_in'  => $quickIn,
                    'cuisine'   => $cuisine,
                    'min_rating'=> $minRating,
                    'size'      => $size,
                ],
                'data' => $data,
            ]);
        });
    }

    /**
     * GET /api/v1/restaurants/{id}
     * Query params:
     * - items_limit : int (defaults to 24) how many items to include per menu
     * Returns restaurant details (if active) with its menus and available items.
     * This gives the menu page enough to render without extra calls.
     */
    public function show(int $id, Request $r)
    {
        $itemsLimit = (int) ($r->input('items_limit') ?? 24);
        if ($itemsLimit < 1) $itemsLimit = 24;

        $ckey = 'rest:show:' . $id . ':limit:' . $itemsLimit;

        return Cache::remember($ckey, now()->addSeconds(30), function () use ($id, $itemsLimit) {
            // Only active restaurants are visible
            $restaurant = Restaurant::query()
                ->where('is_active', true)
                ->findOrFail($id);

            // Eager-load menus with available items (capped per menu)
            $restaurant->load(['menus' => function ($q) use ($itemsLimit) {
                $q->orderBy('id', 'asc')
                  ->with(['items' => function ($iq) use ($itemsLimit) {
                      $iq->where('is_available', true)
                         ->orderByDesc('rating')
                         ->take($itemsLimit);
                  }]);
            }]);

            return response()->json([
                'ok'   => true,
                'data' => $restaurant,
            ]);
        });
    }
}
