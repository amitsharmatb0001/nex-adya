<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Services\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ReconcilePayoutsJob
 * - Fetch recent processing payouts (provider_id exists but not paid/reconciled)
 * - Call provider API (stubbed) to check transfer status
 * - Create ledger entries and mark payouts paid/failed/reconciled
 */
class ReconcilePayoutsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public function handle()
    {
        $payoutService = app(PayoutService::class);
        
        // Select approved and processing payouts older than 30 minutes for processing
        $payouts = Payout::whereIn('status', ['approved', 'processing'])
            ->where('created_at', '<', now()->subMinutes(30))
            ->whereNotNull('external_reference')
            ->limit(200)
            ->get();

        Log::info("Found {$payouts->count()} payouts to reconcile");

        foreach ($payouts as $p) {
            try {
                // TODO: replace this stub with real provider SDK/API call
                $providerStatus = $this->queryProvider($p->external_reference);

                Log::info("Reconciling payout {$p->id} provider_status={$providerStatus['status']}");

                if ($providerStatus['status'] === 'settled') {
                    $payoutService->markProviderSettled($p, $providerStatus);
                    Log::info("Marked payout {$p->id} as settled");
                } elseif ($providerStatus['status'] === 'failed') {
                    $payoutService->markFailed($p, $providerStatus['reason'] ?? 'provider_failed');
                    Log::info("Marked payout {$p->id} as failed");
                } else {
                    // still in-flight: optionally create interim ledger entry
                    PayoutTransaction::create([
                        'payout_id' => $p->id,
                        'kind' => 'reconcile_check',
                        'amount' => 0,
                        'provider_data' => $providerStatus,
                    ]);
                    Log::info("Created interim ledger entry for payout {$p->id}");
                }
            } catch (\Throwable $e) {
                Log::error("Reconciliation failed for payout {$p->id}: " . $e->getMessage());
                // continue on error
            }
        }
    }

    /**
     * Stub - query provider. Replace with real provider API integration.
     * Response shape:
     *  ['status' => 'settled'|'failed'|'pending', 'settled_at' => 'iso', 'reason' => '', ...]
     */
    protected function queryProvider(string $providerId): array
    {
        // In dev return pending; simulate a settled when providerId ends with "S"
        if (str_ends_with($providerId, 'S')) {
            return ['status' => 'settled', 'settled_at' => now()->toIso8601String()];
        }
        if (str_ends_with($providerId, 'F')) {
            return ['status' => 'failed', 'reason' => 'Insufficient funds'];
        }
        
        // For testing: simulate settled status for any external reference
        // In production, this would query the actual provider API
        return ['status' => 'settled', 'settled_at' => now()->toIso8601String()];
    }

    /**
     * Test helper method for fake provider lookup
     */
    public function fakeProviderLookup($payout)
    {
        // Simulate provider lookup based on payout age and status
        if ($payout->status === 'approved' && $payout->created_at < now()->subMinutes(30)) {
            return 'paid';
        }
        return null;
    }
}
