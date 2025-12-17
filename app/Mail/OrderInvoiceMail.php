<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        // Generate PDF from view
        $pdf = Pdf::loadView('pdf.invoice', ['order' => $this->order]);

        return $this->subject('Your Invoice for Order #' . $this->order->id)
            ->markdown('emails.orders.invoice', ['order' => $this->order])
            ->attachData($pdf->output(), 'invoice_order_' . $this->order->id . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
