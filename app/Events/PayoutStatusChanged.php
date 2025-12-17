<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payout;

class PayoutStatusChanged
{
    use Dispatchable, SerializesModels;

    public Payout $payout;
    public string $oldStatus;
    public string $newStatus;

    public function __construct(Payout $payout, string $oldStatus, string $newStatus)
    {
        $this->payout = $payout;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}
