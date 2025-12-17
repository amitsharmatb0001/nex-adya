<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\ReviewDeletionRequest;

class ReviewAdminController extends Controller
{
    // GET /api/v3/reviews
    public function index(Request $req)
    {
        $q = Review::with(['user:id,name,email', 'restaurant:id,name'])
            ->orderByDesc('created_at');

        if ($req->has('visible')) {
            $q->where('is_visible', filter_var($req->query('visible'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['ok' => true, 'data' => $q->paginate(50)]);
    }

    // POST /api/v3/reviews/{id}/delete
    public function delete(int $id)
    {
        $row = Review::findOrFail($id);
        $row->deleted_by_admin = true;
        $row->deleted_at = now();
        $row->save();

        return response()->json(['ok'=>true,'message'=>'Review deleted']);
    }

    // POST /api/v3/reviews/{id}/restore
    public function restore(int $id)
    {
        $row = Review::withTrashed()->findOrFail($id);
        $row->deleted_by_admin = false;
        $row->deleted_at = null;
        $row->save();

        return response()->json(['ok'=>true,'message'=>'Review restored']);
    }

    /**
     * PATCH /api/v3/reviews/{id}/visibility
     * Hide/unhide a review
     */
    public function toggleVisibility(int $id, Request $req)
    {
        $review = Review::findOrFail($id);
        $review->is_visible = $req->boolean('is_visible');
        $review->save();

        return response()->json(['ok' => true, 'data' => $review]);
    }

    /**
     * DELETE /api/v3/reviews/{id}
     * Remove review permanently
     */
    public function destroy(int $id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json(['ok' => true, 'message' => 'Review deleted']);
    }

    // GET /api/v3/review-deletion-requests
    public function deletionRequests()
    {
        $data = ReviewDeletionRequest::with(['review', 'owner', 'admin'])
            ->latest()
            ->paginate(25);
        
        return response()->json(['ok' => true, 'data' => $data]);
    }

    // POST /api/v3/review-deletion-requests/{id}/approve
    public function approveDeletionRequest(int $id, Request $request)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $deletionRequest = ReviewDeletionRequest::findOrFail($id);
        $deletionRequest->status = 'approved';
        $deletionRequest->admin_id = auth()->id();
        $deletionRequest->admin_notes = $request->input('admin_notes');
        $deletionRequest->save();

        // Delete the review
        $deletionRequest->review->delete();

        return response()->json([
            'ok' => true,
            'data' => $deletionRequest,
            'message' => 'Review deletion request approved and review deleted'
        ]);
    }

    // POST /api/v3/review-deletion-requests/{id}/reject
    public function rejectDeletionRequest(int $id, Request $request)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $deletionRequest = ReviewDeletionRequest::findOrFail($id);
        $deletionRequest->status = 'rejected';
        $deletionRequest->admin_id = auth()->id();
        $deletionRequest->admin_notes = $request->input('admin_notes');
        $deletionRequest->save();

        return response()->json([
            'ok' => true,
            'data' => $deletionRequest,
            'message' => 'Review deletion request rejected'
        ]);
    }
}
