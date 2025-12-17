<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * WHY: Simple readiness check for Postman, uptime monitors, and k8s probes.
     * WHAT it returns: ok flag, service name, api version, current time.
     * HOW it's used: GET /api/v1/health -> quick JSON without DB work (fast + safe).
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'ok'      => true,
            'service' => 'Nexadya API',
            'version' => 'v1',
            'time'    => now()->toIso8601String(),
        ], 200);
    }
}
