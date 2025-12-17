<?php

namespace App\Http\Controllers\Api\V2\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Menu\StoreMenuRequest;
use App\Http\Requests\Api\V2\Menu\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\RestaurantOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    /**
     * List menus for the restaurant the authenticated owner belongs to.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // find owner's restaurant (assumes RestaurantOwner exists)
        $owner = RestaurantOwner::where('user_id', $user->id)->firstOrFail();

        $menus = Menu::where('restaurant_id', $owner->restaurant_id)
                    ->orderByDesc('id')
                    ->get();

        return response()->json(['ok' => true, 'data' => $menus]);
    }

    /**
     * Create a menu.
     */
    public function store(StoreMenuRequest $request)
    {
        $user = $request->user();
        $owner = RestaurantOwner::where('user_id', $user->id)->firstOrFail();

        $payload = $request->validated();
        $payload['restaurant_id'] = $owner->restaurant_id;
        $payload['slug'] = Str::slug($payload['title'] . '-' . uniqid());

        $menu = Menu::create($payload);

        return response()->json(['ok' => true, 'data' => $menu], 201);
    }

    /**
     * Update a menu (only owner of restaurant).
     */
    public function update(UpdateMenuRequest $request, int $id)
    {
        $user = $request->user();
        $owner = RestaurantOwner::where('user_id', $user->id)->firstOrFail();

        $menu = Menu::where('restaurant_id', $owner->restaurant_id)->findOrFail($id);

        $menu->update($request->validated());

        return response()->json(['ok' => true, 'data' => $menu]);
    }

    /**
     * Delete a menu (soft delete not implemented — hard delete).
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $owner = RestaurantOwner::where('user_id', $user->id)->firstOrFail();

        $menu = Menu::where('restaurant_id', $owner->restaurant_id)->findOrFail($id);
        $menu->delete();

        return response()->json(['ok' => true, 'message' => 'Menu removed']);
    }
}
