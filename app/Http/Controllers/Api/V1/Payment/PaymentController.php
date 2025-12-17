<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use App\Services\FraudDetectionService;     // Fraud Detection Service      // Fraud Detection Service       // Fraud Detection Service

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /**
     * POST /api/v1/payments/initiate
     * Creates a new payment (mock) for an order.
     */
    public function initiate(Request $request)
    {
        $fraudService = new FraudDetectionService();
        $fraudService->checkPaymentFailures($request->user()->id);

            $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $user  = $request->user();

        if ($order->user_id !== $user->id) {
            abort(403, 'You cannot pay for this order.');
        }

        $payment = $this->payments->initiate(
            userId: $user->id,
            orderId: $order->id,
            amount: $order->total
        );

        return response()->json([
            'ok'   => true,
            'data' => [
                'payment_id' => $payment->id,
                'status'     => $payment->status,
                'amount'     => $payment->amount,
                'currency'   => $payment->currency,
            ],
        ]);
    }

    /**
     * POST /api/v1/payments/{id}/verify
     * Simulates verifying payment success/failure.
     */
    public function verify(Request $request, int $id)
    {
        $request->validate([
            'success' => 'required|boolean',
        ]);

        $payment = Payment::findOrFail($id);
        $user    = $request->user();

        if ($payment->user_id !== $user->id) {
            abort(403, 'You cannot verify this payment.');
        }

        $payment = $this->payments->verify($payment, $request->boolean('success'));

        return response()->json([
            'ok'   => true,
            'data' => [
                'payment_id' => $payment->id,
                'status'     => $payment->status,
            ],
        ]);
    }

    /**
     * GET /api/v1/payments/{id}
     * Fetch current payment status.
     */
    public function status(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->user_id !== $request->user()->id) {
            abort(403, 'Not your payment.');
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'payment_id' => $payment->id,
                'status'     => $payment->status,
                'amount'     => $payment->amount,
                'currency'   => $payment->currency,
            ],
        ]);
    }
}
