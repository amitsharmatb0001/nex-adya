<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\NexmoMessage; // optional for SMS
use App\Models\Payout;

class PayoutStatusNotification extends Notification
{
    use Queueable;

    protected Payout $payout;
    protected string $oldStatus;
    protected string $newStatus;

    /**
     * @param Payout $payout
     * @param string $oldStatus
     * @param string $newStatus
     */
    public function __construct(Payout $payout, string $oldStatus, string $newStatus)
    {
        $this->payout = $payout;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function via($notifiable)
    {
        // send mail + database + broadcast + (push via a custom service)
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable)
    {
        $amount = number_format($this->payout->amount / 100, 2);

        return (new MailMessage)
            ->subject("Payout {$this->payout->id} — {$this->newStatus}")
            ->line("Your payout request #{$this->payout->id} for ₹{$amount} is now **{$this->newStatus}**.")
            ->line("Requested on: " . $this->payout->created_at?->toIso8601String())
            ->when($this->payout->approved_at, fn($mail) => $mail->line("Approved at: " . $this->payout->approved_at->toIso8601String()))
            ->line($this->payout->notes ?? '')
            ->action('View in app', url('/')) // replace with real dashboard URL
            ->line('If you did not expect this, contact support.');
    }

    public function toArray($notifiable)
    {
        return [
            'payout_id' => $this->payout->id,
            'restaurant_id' => $this->payout->restaurant_id,
            'amount' => $this->payout->amount,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'approved_by' => $this->payout->approved_by,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
