<?php

namespace App\Http\Controllers\Api\V1\Wishlist;

use App\Http\Controllers\Controller;
use App\Models\{Wishlist, Restaurant, MenuItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Add to wishlist
     * POST /api/v1/restaurants/{id}/wishlist
     * POST /api/v1/menu-items/{id}/wishlist
     */
    public function store(Request $request, string $type, int $id)
    {
        $user = Auth::user();
        $wishlistable = $this->resolveWishlistable($type, $id);

        $wishlist = Wishlist::firstOrCreate([
            'user_id'            => $user->id,
            'wishlistable_id'    => $wishlistable->id,
            'wishlistable_type'  => get_class($wishlistable),
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $wishlist,
        ], 201);
    }

    /**
     * Remove from wishlist
     */
    public function destroy(string $type, int $id)
    {
        $user = Auth::user();
        $wishlistable = $this->resolveWishlistable($type, $id);

        Wishlist::where('user_id', $user->id)
            ->where('wishlistable_id', $wishlistable->id)
            ->where('wishlistable_type', get_class($wishlistable))
            ->delete();

        return response()->json([
            'ok' => true,
            'data' => ['message' => 'Removed from wishlist'],
        ]);
    }

    /**
     * Get wishlist items
     * GET /api/v1/wishlist
     */
    public function index()
    {
        $items = Wishlist::where('user_id', Auth::id())
            ->with('wishlistable')
            ->latest()
            ->paginate(10);

        return response()->json([
            'ok'   => true,
            'data' => $items,
        ]);
    }

    private function resolveWishlistable(string $type, int $id)
    {
        return match($type) {
            'restaurant' => Restaurant::findOrFail($id),
            'menu-item'  => MenuItem::findOrFail($id),
            default      => abort(404, 'Invalid wishlist type'),
        };
    }
}
