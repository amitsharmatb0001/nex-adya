<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use App\Services\ActivityLogService;

class TrustedDeviceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/v2/trusted-devices
     * List current user's trusted devices
     */
    public function index(Request $request)
    {
        $devices = TrustedDevice::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderByDesc('last_used_at')
            ->get(['id','device_name','last_used_at','expires_at','ip','user_agent']);

        return response()->json(['ok' => true, 'data' => $devices]);
    }

    /**
     * DELETE /api/v2/trusted-devices/{id}
     * Revoke a trusted device owned by user
     */
    public function destroy(Request $request, int $id, TrustedDeviceService $service)
    {
        $device = TrustedDevice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $service->revoke($device);

        ActivityLogService::log('trusted_device_revoked', [
            'user_id' => $request->user()->id,
            'device_id' => $id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Trusted device revoked.']);
    }

    /**
     * Admin endpoint (optional):
     * GET /api/v2/admin/trusted-devices
     * List all trusted devices for audit
     */
    public function adminIndex()
    {
        $this->authorize('admin-only'); // you can replace with middleware role check

        $devices = TrustedDevice::with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json(['ok' => true, 'data' => $devices]);
    }
}
