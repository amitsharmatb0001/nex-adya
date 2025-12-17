<?php

namespace App\Http\Controllers\Api\V1\Review;

use App\Http\Controllers\Controller;
use App\Models\{Review, Restaurant, MenuItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * POST /api/v1/restaurants/{id}/reviews
     */
    public function storeRestaurant(Request $request, int $id)
    {
        return $this->store($request, 'restaurant', $id);
    }

    /**
     * POST /api/v1/menu-items/{id}/reviews
     */
    public function storeMenuItem(Request $request, int $id)
    {
        return $this->store($request, 'menu-item', $id);
    }

    /**
     * Internal store method
     */
    private function store(Request $request, string $type, int $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        $reviewable = $this->resolveReviewable($type, $id);

        // Verify that user has at least one delivered order from this restaurant
        if ($type === 'restaurant') {
            $hasOrdered = \App\Models\Order::where('user_id', $user->id)
                ->where('restaurant_id', $id)
                ->where('status', 'delivered')
                ->exists();
        } else {
            // For menu items, check if user has ordered this specific item
            $hasOrdered = \App\Models\OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'delivered');
            })->where('menu_item_id', $id)->exists();
        }

        if (!$hasOrdered) {
            $message = $type === 'restaurant' 
                ? 'You can only review restaurants you have ordered from.'
                : 'You can only review items you have purchased.';
                
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'NOT_VERIFIED_PURCHASE', 'message' => $message]
            ], 403);
        }

        $review = Review::updateOrCreate(
            [
                'user_id'         => $user->id,
                'reviewable_id'   => $reviewable->id,
                'reviewable_type' => get_class($reviewable),
            ],
            [
                'rating'  => $request->rating,
                'comment' => $request->comment,
            ]
        );

        // Notify restaurant owners about the new review
        $restaurantId = $type === 'restaurant' ? $id : $reviewable->restaurant_id;
        $owners = \App\Models\RestaurantOwner::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get();

        foreach ($owners as $owner) {
            $owner->user->notify(new \App\Notifications\NewReviewNotification($review));
        }

        return response()->json([
            'ok'   => true,
            'data' => $review,
        ], 201);
    }

    /**
     * GET /api/v1/restaurants/{id}/reviews
     */
    public function indexRestaurant(int $id)
    {
        return $this->index('restaurant', $id);
    }

    /**
     * GET /api/v1/menu-items/{id}/reviews
     */
    public function indexMenuItem(int $id)
    {
        return $this->index('menu-item', $id);
    }

    /**
     * Internal index method
     */
    private function index(string $type, int $id)
    {
        $reviewable = $this->resolveReviewable($type, $id);

        return response()->json([
            'ok'   => true,
            'data' => $reviewable->reviews()->with('user')->latest()->paginate(10),
        ]);
    }

    /**
     * PUT /api/v1/reviews/{id}
     */
    public function update(Request $request, int $id)
    {
        $review = Review::findOrFail($id);
        
        $this->authorize('update', $review);
        
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);
        
        $review->update([
            'rating'  => $request->rating,
            'comment' => $request->comment,
        ]);
        
        return response()->json([
            'ok'   => true,
            'data' => $review,
        ]);
    }

    /**
     * DELETE /api/v1/reviews/{id}
     */
    public function destroy(int $id)
    {
        $review = Review::where('user_id', Auth::id())->findOrFail($id);

        $review->delete();

        return response()->json([
            'ok'   => true,
            'data' => ['message' => 'Review deleted'],
        ]);
    }

    private function resolveReviewable(string $type, int $id)
    {
        return match($type) {
            'restaurant' => Restaurant::findOrFail($id),
            'menu-item'  => MenuItem::findOrFail($id),
            default      => abort(404, 'Invalid review type'),
        };
    }
}
