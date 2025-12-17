<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payout;

class PayoutStatusMail extends Mailable
{
    use SerializesModels;

    public Payout $payout;
    public string $oldStatus;
    public string $newStatus;

    public function __construct(Payout $payout, string $oldStatus, string $newStatus)
    {
        $this->payout = $payout;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function build()
    {
        return $this->subject("Payout #{$this->payout->id} - {$this->newStatus}")
            ->markdown('emails.payouts.status', [
                'payout' => $this->payout,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
            ]);
    }
}
