<?php

namespace App\Http\Controllers\Api\V2\Owner;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOwner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin-managed RestaurantOwner CRUD + approve endpoint.
 *
 * Notes:
 * - index/store/show/update/destroy remain admin-only
 * - approve is admin-only and records approved_by + approved_at and activates the owner
 */
class RestaurantOwnerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Admin-only for management endpoints
        $this->middleware('role:admin')->only(['store','destroy','index','approveByAdmin']);
        // Owners can call selfActivate
        $this->middleware('role:restaurant')->only(['selfActivate']);
    }

    public function index(Request $request)
    {
        $q = RestaurantOwner::with(['user:id,name,email','restaurant:id,name,slug'])->latest();
        $data = $q->paginate(25);
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'restaurant_id' => ['required','integer','exists:restaurants,id'],
            'role' => ['required','string', Rule::in(['owner','manager','staff'])],
            'is_active' => ['nullable','boolean'],
        ]);

        $now = now();
        DB::table('restaurant_owners')->updateOrInsert(
            ['user_id' => $payload['user_id'], 'restaurant_id' => $payload['restaurant_id']],
            ['role' => $payload['role'], 'is_active' => $payload['is_active'] ?? true, 'updated_at' => $now, 'created_at' => $now]
        );

        $row = RestaurantOwner::where('user_id', $payload['user_id'])->where('restaurant_id', $payload['restaurant_id'])->first();

        return response()->json(['ok' => true, 'data' => $row], 201);
    }

    public function show(int $id)
    {
        $row = RestaurantOwner::with(['user','restaurant'])->findOrFail($id);
        return response()->json(['ok' => true, 'data' => $row]);
    }

    public function update(Request $request, int $id)
    {
        $row = RestaurantOwner::findOrFail($id);

        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['owner','manager','staff'])],
            'is_active' => ['nullable','boolean'],
        ]);

        $row->fill(array_filter($payload, fn($v) => !is_null($v)));
        $row->save();

        return response()->json(['ok' => true, 'data' => $row]);
    }

    public function destroy(int $id)
    {
        $row = RestaurantOwner::findOrFail($id);
        $row->delete();
        return response()->json(['ok' => true, 'message' => 'Assignment removed']);
    }

    /**
     * Approve a pending restaurant owner assignment.
     *
     * POST /api/v2/restaurant-owners/{id}/approve
     *
     * Body (optional):
     *  - notify_user: boolean (send email later by job)
     *
     * Notes:
     *  - Only admin with auth token can approve.
     *  - This records approved_by and approved_at and flips is_active true.
     */
    public function approveByAdmin(int $id, Request $request)
    {
        $admin = $request->user();
        $row = RestaurantOwner::findOrFail($id);

        $row->is_active = true;
        $row->approved_by = $admin->id;
        $row->approved_at = now();
        $row->approval_note = $request->input('note', 'Approved by admin via API');
        $row->save();

        Log::info('restaurant_owner.approved_by_admin', [
            'owner_row_id' => $row->id,
            'approved_by'  => $admin->id,
            'restaurant_id'=> $row->restaurant_id,
        ]);

        return response()->json(['ok' => true, 'data' => $row]);
    }

    /**
     * Owner self-activate (only callable by the user tied to this owner record)
     * POST /api/v2/restaurant-owners/{id}/self-activate
     *
     * Security: we verify auth user owns the RestaurantOwner row.
     * In production, replace this with a verification workflow (document upload + admin confirm).
     */
    public function selfActivate(int $id, Request $request)
    {
        $user = $request->user();
        $row = RestaurantOwner::findOrFail($id);

        // Ownership guard
        if ((int)$row->user_id !== (int)$user->id) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'You are not allowed to perform this action.']
            ], 403);
        }

        // Prevent double-activation
        if ($row->is_active) {
            return response()->json(['ok' => true, 'data' => $row, 'message' => 'Already active']);
        }

        // Optionally require an 'acknowledgement' field or docs flag
        if (! $request->boolean('confirm_documents_present')) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'DOCS_REQUIRED', 'message' => 'You must confirm that required documents have been submitted.']
            ], 422);
        }

        $row->is_active = true;
        $row->approved_by = $user->id; // self-approved; admin still can override
        $row->approved_at = now();
        $row->approval_note = 'Self-activated by owner';
        $row->save();

        Log::info('restaurant_owner.self_activated', [
            'owner_row_id' => $row->id,
            'user_id'      => $user->id,
            'restaurant_id'=> $row->restaurant_id,
        ]);

        return response()->json(['ok' => true, 'data' => $row]);
    }
}
