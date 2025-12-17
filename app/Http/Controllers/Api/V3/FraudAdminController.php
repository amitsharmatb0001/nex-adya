<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FraudLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FraudAdminController extends Controller
{
    // GET /api/v3/fraud-logs
    public function index()
    {
        $data = FraudLog::latest()->paginate(50);
        return response()->json(['ok'=>true,'data'=>$data]);
    }

    // POST /api/v3/fraud-logs/{id}/flag
    public function flag(Request $r, int $id)
    {
        $row = FraudLog::findOrFail($id);
        $row->flagged = true;
        $row->flagged_by = $r->user()->id;
        $row->flagged_at = now();
        $row->save();

        return response()->json(['ok'=>true,'data'=>$row]);
    }

    /**
     * Trigger retraining of the ML fraud model.
     * Calls the Python FastAPI service: POST /retrain
     */
    public function retrain(Request $request)
    {
        try {
            // Call your Python API
            $response = Http::timeout(120) // allow longer runtime
                ->post('http://127.0.0.1:8001/retrain');

            if ($response->failed()) {
                Log::error('Fraud retrain failed', ['response' => $response->body()]);
                return response()->json([
                    'ok' => false,
                    'error' => 'Retrain failed at ML service'
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Fraud model retraining triggered successfully',
                'ml_response' => $response->json(),
            ]);
        } catch (Throwable $e) {
            Log::error('Fraud retrain exception', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'Exception while retraining model'
            ], 500);
        }
    }
}
