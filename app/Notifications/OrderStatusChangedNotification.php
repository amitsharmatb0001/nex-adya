<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * OrderStatusChangedNotification
 *
 * Channels: mail + database
 * Payload (stored in database): [
 *   'order_id' => int,
 *   'old_status' => string,
 *   'new_status' => string,
 *   'total' => int (paise),
 *   'message' => string // human readable
 * ]
 *
 * NOTE: The listener constructs payload and calls Notification::send($notifiable, new OrderStatusChangedNotification($payload, $for));
 */
class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $payload;
    protected string $for; // 'user' or 'restaurant'

    public function __construct(array $payload, string $for = 'user')
    {
        $this->payload = $payload;
        $this->for = $for;
        // queue on default connection; can set queue/connection via ->onQueue() where dispatched
    }

    public function via($notifiable)
    {
        // Send mail if notifiable has email; always store DB entry
        $channels = ['database'];
        if (property_exists($notifiable, 'email') && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail($notifiable)
    {
        $p = $this->payload;
        $rupee = number_format(($p['total'] ?? 0) / 100, 2);

        $mail = (new MailMessage)
            ->subject("Order #{$p['order_id']} — status updated: {$p['new_status']}")
            ->line("Order #{$p['order_id']} status changed from {$p['old_status']} to {$p['new_status']}.")
            ->line("Order total: ₹{$rupee}")
            ->action('View order', url("/orders/{$p['order_id']}")) // front-end link (replace base)
            ->line('Thank you for using our service.');

        // If restaurant side notification, tweak wording
        if ($this->for === 'restaurant') {
            $mail->line('This is an update for your restaurant order queue.');
        }

        return $mail;
    }

    public function toDatabase($notifiable)
    {
        $p = $this->payload;
        return [
            'order_id'  => $p['order_id'] ?? null,
            'old_status'=> $p['old_status'] ?? null,
            'new_status'=> $p['new_status'] ?? null,
            'total'     => $p['total'] ?? 0,
            'message'   => $p['message'] ?? "Order status changed to {$p['new_status']}",
            'for'       => $this->for,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->payload['order_id'],
            'old_status' => $this->payload['old_status'],
            'new_status' => $this->payload['new_status'],
            'total' => (int) $this->payload['total'],
        ];
    }
}
