<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\Transaction;
use App\Events\PayoutStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessPayoutJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected int $payoutId;

    public function __construct(int $payoutId)
    {
        $this->payoutId = $payoutId;
    }

    public function handle(): void
    {
        $payout = Payout::find($this->payoutId);
        if (!$payout || $payout->status !== 'approved') {
            return;
        }

        DB::transaction(function () use ($payout) {
            $old = $payout->status;

            // Simulate provider call — always succeed for now
            $providerStatus = 'paid';
            $reference = 'SIM-' . uniqid();

            if ($providerStatus === 'paid') {
                $payout->update([
                    'status'     => 'paid',
                    'paid_at'    => now(),
                    'external_reference' => $reference,
                ]);

                Transaction::create([
                    'payout_id'     => $payout->id,
                    'restaurant_id' => $payout->restaurant_id,
                    'user_id'       => $payout->requested_by,
                    'type'          => 'payout',
                    'amount'        => $payout->amount,
                    'currency'      => $payout->currency,
                    'reference'     => $reference,
                ]);

                event(new PayoutStatusChanged($payout, $old, 'paid'));
                Log::info('ProcessPayoutJob: payout marked paid', ['payout' => $payout->id]);
            }
        });
    }
}
