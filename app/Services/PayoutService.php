<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    /**
     * Create payout request and ledger entry.
     */
    public function createRequest(int $restaurantId, int $amountPaise, array $meta = [], ?string $note = null): Payout
    {
        return DB::transaction(function () use ($restaurantId, $amountPaise, $meta, $note) {
            $payout = Payout::create([
                'restaurant_id' => $restaurantId,
                'amount' => $amountPaise,
                'currency' => $meta['currency'] ?? 'INR',
                'status' => 'pending',
                'metadata' => $meta,
                'note' => $note,
            ]);

            // ledger: requested
            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'kind' => 'requested',
                'amount' => $amountPaise,
                'running_balance' => null,
                'provider_data' => null,
            ]);

            return $payout;
        });
    }

    /**
     * Approve and mark as processing; create ledger entry and return payout.
     * Admin only.
     */
    public function approve(Payout $payout, int $adminUserId, array $providerHint = [], ?string $note = null): Payout
    {
        return DB::transaction(function () use ($payout, $adminUserId, $providerHint, $note) {
            if ($payout->status !== 'pending') {
                throw new \RuntimeException("Only pending payouts can be approved.");
            }

            $payout->status = 'processing';
            $payout->approved_by = $adminUserId;
            $payout->approved_at = now();
            if ($note) $payout->note = $note;
            $payout->save();

            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'kind' => 'approved',
                'amount' => 0,
                'provider_data' => $providerHint,
            ]);

            return $payout;
        });
    }

    /**
     * Reject a payout.
     * Admin only.
     */
    public function reject(Payout $payout, int $adminUserId, ?string $reason = null): Payout
    {
        return DB::transaction(function () use ($payout, $adminUserId, $reason) {
            if ($payout->status !== 'pending') {
                throw new \RuntimeException("Only pending payouts can be rejected.");
            }

            $payout->status = 'rejected';
            $payout->rejected_by = $adminUserId; // Assumes 'rejected_by' column exists
            $payout->rejected_at = now(); // Assumes 'rejected_at' column exists
            if ($reason) $payout->notes = $reason;
            $payout->save();

            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'kind' => 'rejected',
                'amount' => 0,
                'provider_data' => ['reason' => $reason],
            ]);

            return $payout;
        });
    }

    /**
     * Mark as sent (when payment provider returns transfer id) and ledger sent event.
     */
    public function markSent(Payout $payout, string $providerId, array $providerResponse = []): Payout
    {
        return DB::transaction(function () use ($payout, $providerId, $providerResponse) {
            $payout->status = 'processing';
            $payout->provider_id = $providerId;
            $payout->save();

            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'kind' => 'sent',
                'amount' => -1 * abs($payout->amount), // money out
                'provider_data' => $providerResponse,
                'reference' => $providerId,
            ]);

            return $payout;
        });
    }

    /**
     * Called by reconciliation when provider marks settled.
     */
    public function markProviderSettled(Payout $payout, array $providerResponse = []): Payout
    {
        return DB::transaction(function () use ($payout, $providerResponse) {
            $payout->status = 'paid';
            $payout->paid_at = now();
            $payout->save();

            Transaction::create([
                'payout_id' => $payout->id,
                'restaurant_id' => $payout->restaurant_id,
                'user_id' => $payout->requested_by,
                'type' => 'payout',
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'reference' => ($providerResponse['settled_at'] ?? now()->toIso8601String()) . '_' . $payout->id,
                'meta' => $providerResponse,
            ]);

            return $payout;
        });
    }

    /**
     * Flag payout failed
     */
    public function markFailed(Payout $payout, string $reason = null): Payout
    {
        return DB::transaction(function () use ($payout, $reason) {
            $payout->status = 'failed';
            $payout->save();

            PayoutTransaction::create([
                'payout_id' => $payout->id,
                'kind' => 'failed',
                'amount' => 0,
                'provider_data' => ['reason' => $reason],
            ]);

            return $payout;
        });
    }
}
