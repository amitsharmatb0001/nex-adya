<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Create a new payment request (mocked for dev).
     */
    public function initiate(int $userId, int $orderId, int $amount): Payment
    {
        return DB::transaction(function () use ($userId, $orderId, $amount) {
            return Payment::create([
                'user_id'  => $userId,
                'order_id' => $orderId,
                'provider' => 'mock',
                'status'   => 'pending',
                'amount'   => $amount,
                'currency' => 'INR',
            ]);
        });
    }

    /**
     * Mark payment as verified or failed (simulated).
     */
    public function verify(Payment $payment, bool $success): Payment
    {
        $payment->status = $success ? 'paid' : 'failed';
        $payment->save();

        return $payment;
    }

    /**
     * Get current status.
     */
    public function status(Payment $payment): Payment
    {
        return $payment;
    }
}
