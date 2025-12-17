<?php

namespace App\Http\Controllers\Api\V4\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryPartnerAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * GET /api/v4/admin/delivery-partners
     * List all delivery partners with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = DeliveryPartner::with(['user:id,name,email,phone'])
            ->orderByDesc('created_at');

        // Filters
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('available')) {
            $query->where('is_available', $request->boolean('available'));
        }

        if ($request->has('vehicle_type')) {
            $query->where('vehicle_type', $request->vehicle_type);
        }

        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        $data = $query->paginate(25)->appends($request->query());
        
        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * POST /api/v4/admin/delivery-partners
     * Create a new delivery partner
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'license_number' => 'required|string|unique:delivery_partners,license_number',
            'vehicle_type' => 'required|in:bike,car,scooter',
            'vehicle_number' => 'required|string',
            'phone' => 'required|string|unique:delivery_partners,phone',
            'documents' => 'required|array',
            'documents.license' => 'required|string',
            'documents.insurance' => 'required|string',
        ]);

        // Ensure user has delivery role
        $user = User::findOrFail($request->user_id);
        $user->update(['role' => 'delivery']);

        $deliveryPartner = DeliveryPartner::create($request->all());

        return response()->json(['ok' => true, 'data' => $deliveryPartner->load('user')], 201);
    }

    /**
     * GET /api/v4/admin/delivery-partners/{id}
     * Show delivery partner details
     */
    public function show(DeliveryPartner $deliveryPartner)
    {
        $deliveryPartner->load(['user', 'deliveries' => function($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json(['ok' => true, 'data' => $deliveryPartner]);
    }

    /**
     * PUT /api/v4/admin/delivery-partners/{id}
     * Update delivery partner
     */
    public function update(Request $request, DeliveryPartner $deliveryPartner)
    {
        $request->validate([
            'license_number' => 'sometimes|string|unique:delivery_partners,license_number,' . $deliveryPartner->id,
            'vehicle_type' => 'sometimes|in:bike,car,scooter',
            'vehicle_number' => 'sometimes|string',
            'phone' => 'sometimes|string|unique:delivery_partners,phone,' . $deliveryPartner->id,
            'documents' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $deliveryPartner->update($request->all());

        return response()->json(['ok' => true, 'data' => $deliveryPartner->load('user')]);
    }

    /**
     * DELETE /api/v4/admin/delivery-partners/{id}
     * Deactivate delivery partner
     */
    public function destroy(DeliveryPartner $deliveryPartner)
    {
        $deliveryPartner->update(['is_active' => false]);
        
        return response()->json(['ok' => true, 'message' => 'Delivery partner deactivated']);
    }

    /**
     * POST /api/v4/admin/delivery-partners/{id}/activate
     * Activate delivery partner
     */
    public function activate(DeliveryPartner $deliveryPartner)
    {
        $deliveryPartner->update(['is_active' => true]);
        
        return response()->json(['ok' => true, 'data' => $deliveryPartner]);
    }

    /**
     * GET /api/v4/admin/delivery-partners/available
     * Get available delivery partners
     */
    public function available(Request $request)
    {
        $query = DeliveryPartner::with('user:id,name,phone')
            ->where('is_active', true)
            ->where('is_available', true);

        if ($request->has('vehicle_type')) {
            $query->where('vehicle_type', $request->vehicle_type);
        }

        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        $data = $query->orderBy('rating', 'desc')->get();
        
        return response()->json(['ok' => true, 'data' => $data]);
    }
}
