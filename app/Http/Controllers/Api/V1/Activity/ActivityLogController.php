<?php

namespace App\Http\Controllers\Api\V1\Activity;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * GET /api/v1/activity
     * Paginated user activity logs
     */
    public function index()
    {
        $logs = ActivityLog::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return response()->json([
            'ok'   => true,
            'data' => $logs,
        ]);
    }

    /**
     * GET /api/v1/activity/export
     * Download full log history (DPDP right to data portability)
     */
    public function export()
    {
        $logs = ActivityLog::where('user_id', Auth::id())
            ->orderBy('created_at')
            ->get();

        $filename = 'activity_logs_' . now()->format('Ymd_His') . '.json';
        $json = $logs->toJson(JSON_PRETTY_PRINT);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
    public function destroy(int $id)
    {
        $log = ActivityLog::where('user_id', Auth::id())->findOrFail($id);
        $log->delete();
        return response()->json(['ok' => true, 'message' => 'Activity log deleted.']);
    }                                   
    
}
