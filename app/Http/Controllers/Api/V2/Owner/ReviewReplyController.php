<?php

namespace App\Http\Controllers\Api\V2\Owner;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\Restaurant;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class ReviewReplyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum','role:restaurant']);
    }

    /**
     * POST /api/v2/reviews/{id}/reply
     * Owner can reply to a review
     */
    public function store(Request $req, int $id)
    {
        $req->validate(['reply' => 'required|string|max:1000']);
        $user = $req->user();

        $owner = \App\Models\RestaurantOwner::where('user_id',$user->id)
            ->where('is_active',true)->firstOrFail();

        $review = Review::findOrFail($id);

        // Correctly resolve the restaurant ID from the polymorphic relationship
        $reviewRestaurantId = null;
        if ($review->reviewable_type === Restaurant::class) {
            $reviewRestaurantId = $review->reviewable_id;
        } elseif ($review->reviewable_type === MenuItem::class) {
            $menuItem = MenuItem::find($review->reviewable_id);
            $reviewRestaurantId = $menuItem ? $menuItem->menu->restaurant_id : null;
        }

        if ($reviewRestaurantId !== $owner->restaurant_id) {
            return response()->json(['ok'=>false,'error'=>'Not your restaurant review'],403);
        }

        $reply = ReviewReply::create([
            'review_id' => $review->id,
            'owner_id'  => $owner->id,
            'reply'     => $req->input('reply'),
        ]);

        return response()->json(['ok'=>true,'data'=>$reply],201);
    }

    /**
     * DELETE /api/v2/reviews/{id}/reply
     * Owner can delete own reply
     */
    public function destroy(Request $req, int $id)
    {
        $user = $req->user();
        $owner = \App\Models\RestaurantOwner::where('user_id',$user->id)
            ->where('is_active',true)->firstOrFail();

        $reply = ReviewReply::where('review_id',$id)->where('owner_id',$owner->id)->firstOrFail();
        $reply->delete();

        return response()->json(['ok'=>true,'message'=>'Reply deleted']);
    }

    /**
     * POST /api/v2/reviews/{id}/request-deletion
     * Owner can request deletion of a review due to hate speech or inappropriate content
     */
    public function requestDeletion(Request $req, int $id)
    {
        $req->validate([
            'reason' => 'required|string|max:500',
            'evidence' => 'nullable|string|max:1000'
        ]);

        $user = $req->user();
        $owner = \App\Models\RestaurantOwner::where('user_id',$user->id)
            ->where('is_active',true)->firstOrFail();

        $review = Review::findOrFail($id);

        // Check if the review belongs to the owner's restaurant
        $reviewRestaurantId = null;
        if ($review->reviewable_type === Restaurant::class) {
            $reviewRestaurantId = $review->reviewable_id;
        } elseif ($review->reviewable_type === MenuItem::class) {
            $menuItem = MenuItem::find($review->reviewable_id);
            $reviewRestaurantId = $menuItem ? $menuItem->menu->restaurant_id : null;
        }

        if ($reviewRestaurantId !== $owner->restaurant_id) {
            return response()->json(['ok'=>false,'error'=>'Not your restaurant review'],403);
        }

        // Create a deletion request record
        $deletionRequest = \App\Models\ReviewDeletionRequest::create([
            'review_id' => $review->id,
            'owner_id' => $owner->id,
            'reason' => $req->input('reason'),
            'evidence' => $req->input('evidence'),
            'status' => 'pending'
        ]);

        return response()->json([
            'ok' => true,
            'data' => $deletionRequest,
            'message' => 'Review deletion request submitted for admin review'
        ], 201);
    }
}
