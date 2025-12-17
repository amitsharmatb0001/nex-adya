<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\{Restaurant, MenuItem};

class SearchService
{
    /**
     * Suggest restaurants and items for a given city/state + query.
     * - Matches by city OR state (so user can type "Delhi" or "Rajasthan").
     * - Only active restaurants and available items are returned.
     * - Optional quick filter: eta_minutes <= 30.
     * - Uses a short cache (15s) to smooth keystroke bursts.
     */
    public function suggest(string $city, string $q, int $size = 10, bool $quick = false): array
    {
        $cityKey  = Str::lower($city);
        $qKey     = Str::lower($q);
        $cacheKey = "suggest:{$cityKey}:{$qKey}:{$size}:".($quick ? '1' : '0');

        return Cache::remember($cacheKey, now()->addSeconds(15), function () use ($city, $q, $size, $quick) {
            // --------------------------
            // Restaurants
            // --------------------------
            $restQ = Restaurant::query()
                ->where('is_active', true)
                ->where(function ($query) use ($city) {
                    $query->where('city', 'LIKE', "%{$city}%")
                          ->orWhere('state', 'LIKE', "%{$city}%");
                })
                ->where('name', 'LIKE', "%{$q}%");

            if ($quick) {
                $restQ->where('eta_minutes', '<=', 30);
            }

            $restaurants = $restQ
                ->orderByDesc('rating')
                ->orderBy('eta_minutes')
                ->limit($size)
                ->get(['id','name','slug','image','city','state','rating','eta_minutes']);

            // --------------------------
            // Menu items
            // --------------------------
            $itemQ = MenuItem::query()
                ->where('is_available', true)
                ->where('name', 'LIKE', "%{$q}%")
                ->whereHas('menu.restaurant', function ($q2) use ($city, $quick) {
                    $q2->where('is_active', true)
                       ->where(function ($sub) use ($city) {
                           $sub->where('city', 'LIKE', "%{$city}%")
                               ->orWhere('state', 'LIKE', "%{$city}%");
                       });
                    if ($quick) {
                        $q2->where('eta_minutes', '<=', 30);
                    }
                });

            $items = $itemQ
                ->limit($size)
                ->with(['menu:id,restaurant_id', 'menu.restaurant:id,name,slug,city,state,image,eta_minutes'])
                ->get(['id','menu_id','name','price','veg','image_url']);

            // --------------------------
            // Response shaping
            // --------------------------
            return [
                'restaurants' => $restaurants->map(fn($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'slug'        => $r->slug,
                    'image'       => $r->image,
                    'city'        => $r->city,
                    'state'       => $r->state,
                    'rating'      => $r->rating,
                    'eta_minutes' => $r->eta_minutes,
                ])->values(),

                'items' => $items->map(function ($mi) {
                    $rest = $mi->menu?->restaurant;
                    return [
                        'id'          => $mi->id,
                        'name'        => $mi->name,
                        'price'       => $mi->price,  // paise
                        'veg'         => (bool) $mi->veg,
                        'image_url'   => $mi->image_url,
                        'restaurant'  => $rest ? [
                            'id'          => $rest->id,
                            'name'        => $rest->name,
                            'slug'        => $rest->slug,
                            'city'        => $rest->city,
                            'state'       => $rest->state,
                            'image'       => $rest->image,
                            'eta_minutes' => $rest->eta_minutes,
                        ] : null,
                    ];
                })->values(),
            ];
        });
    }
}
