<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderInvoiceMail;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
        $this->onQueue('invoices');
    }

    public function handle()
    {
        try {
            $order = Order::with(['user','items','restaurant'])->find($this->orderId);
            if (! $order) {
                Log::warning("GenerateInvoiceJob: order not found {$this->orderId}");
                return;
            }

            // TODO: generate PDF invoice and store in storage (e.g. storage/app/invoices/order_{id}.pdf)
            // Example (pseudocode):
            // $pdf = InvoiceService::createFromOrder($order);
            // $path = $pdf->saveToStorage("invoices/order_{$order->id}.pdf");

            // For now just queue an email that will later attach the invoice if present.
            Mail::to($order->user->email)->queue(new OrderInvoiceMail($order));
            Log::info("GenerateInvoiceJob: invoice job queued for order {$this->orderId}");
        } catch (\Throwable $e) {
            Log::error("GenerateInvoiceJob failed for order {$this->orderId}: {$e->getMessage()}");
            // Let job be retried or move to failed jobs depending on config.
            throw $e;
        }
    }
}
