<?php

namespace App\Http\Controllers\Api\V1\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Menu;

class MenuController extends Controller
{
    public function forRestaurant(int $restaurantId)
    {
        $menus = Menu::with(['items' => function ($q) {
                $q->where('is_available', true);
            }])
            ->where('restaurant_id', $restaurantId)
            ->get();

        return response()->json(['ok' => true, 'data' => $menus]);
    }
}
