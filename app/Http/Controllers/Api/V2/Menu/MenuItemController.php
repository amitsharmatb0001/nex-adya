<?php

namespace App\Http\Controllers\Api\V2\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Menu\StoreMenuItemRequest;
use App\Http\Requests\Api\V2\Menu\UpdateMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\RestaurantOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    protected function requireOwnerAndMenu(Request $request, int $menuId): Menu
    {
        $user = $request->user();
        $owner = RestaurantOwner::where('user_id', $user->id)->firstOrFail();
        $menu = Menu::where('restaurant_id', $owner->restaurant_id)->findOrFail($menuId);
        return $menu;
    }

    public function index(Request $request, int $menuId)
    {
        $menu = $this->requireOwnerAndMenu($request, $menuId);
        $items = MenuItem::where('menu_id', $menu->id)->orderBy('name')->get();
        return response()->json(['ok' => true, 'data' => $items]);
    }

    public function store(StoreMenuItemRequest $request, int $menuId)
    {
        $menu = $this->requireOwnerAndMenu($request, $menuId);
        $payload = $request->validated();

        // handle optional image
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu_items', 'public');
            $payload['image_url'] = '/storage/' . $path;
        }

        $payload['menu_id'] = $menu->id;
        $payload['slug'] = Str::slug($payload['name'] . '-' . uniqid());

        $item = MenuItem::create($payload);

        return response()->json(['ok' => true, 'data' => $item], 201);
    }

    public function update(UpdateMenuItemRequest $request, int $menuId, int $id)
    {
        $menu = $this->requireOwnerAndMenu($request, $menuId);
        $item = MenuItem::where('menu_id', $menu->id)->findOrFail($id);

        $payload = $request->validated();

        if ($request->hasFile('image')) {
            // delete old image if exists (best-effort)
            if ($item->image_url) {
                $old = str_replace('/storage/', '', $item->image_url);
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('image')->store('menu_items', 'public');
            $payload['image_url'] = '/storage/' . $path;
        }

        $item->update($payload);

        return response()->json(['ok' => true, 'data' => $item]);
    }

    public function destroy(Request $request, int $menuId, int $id)
    {
        $menu = $this->requireOwnerAndMenu($request, $menuId);
        $item = MenuItem::where('menu_id', $menu->id)->findOrFail($id);

        // remove image from storage (best-effort)
        if ($item->image_url) {
            $old = str_replace('/storage/', '', $item->image_url);
            Storage::disk('public')->delete($old);
        }

        $item->delete();
        return response()->json(['ok' => true, 'message' => 'Item deleted']);
    }

    public function toggleAvailability(Request $request, int $menuId, int $id)
    {
        $menu = $this->requireOwnerAndMenu($request, $menuId);
        $item = MenuItem::where('menu_id', $menu->id)->findOrFail($id);

        $item->is_available = !$item->is_available;
        $item->save();

        return response()->json(['ok' => true, 'data' => ['id' => $item->id, 'is_available' => $item->is_available]]);
    }
}
