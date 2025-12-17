<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrderDeliveredNotification extends Notification
{
    use Queueable;

    protected $order;
    protected $pushOnly;

    public function __construct($order, bool $pushOnly = false)
    {
        $this->order = $order;
        $this->pushOnly = $pushOnly;
    }

    public function via($notifiable)
    {
        $channels = ['database', 'fcm'];
        if (! $this->pushOnly) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable)
    {
        return [
            'order_id' => $this->order->id,
            'type' => 'order_delivered',
            'title' => 'Order Delivered',
            'message' => 'Your order has been delivered. Thank you!',
            'data' => [
                'order_id' => $this->order->id,
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => $this->order->total,
                'status' => $this->order->status,
                'delivered_at' => $this->order->delivered_at?->toIso8601String(),
                'delivery_partner' => $this->order->deliveryPartner->user->name ?? 'Delivery Partner',
            ]
        ];
    }

    public function toFcm($notifiable)
    {
        return [
            'notification' => [
                'title' => 'Order Delivered',
                'body' => "Your order #{$this->order->id} has been delivered successfully!",
                'icon' => 'ic_delivered',
                'sound' => 'default',
            ],
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_delivered',
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => (string) $this->order->total,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'priority' => 'high'
        ];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Your order #{$this->order->id} — Delivered")
            ->greeting('Hello!')
            ->line('Your order has been delivered successfully. Thank you for ordering with us!')
            ->line('Order Details:')
            ->line("Order ID: #{$this->order->id}")
            ->line("Restaurant: {$this->order->restaurant->name}")
            ->line("Total: ₹" . number_format(($this->order->total ?? 0) / 100, 2))
            ->line("Delivered at: " . $this->order->delivered_at?->format('M d, Y \a\t h:i A'))
            ->action('View Order Details', url("/orders/{$this->order->id}"))
            ->line('We hope you enjoyed your meal!')
            ->line('If you have any questions or concerns, please contact our support team.')
            ->salutation('Thank you for choosing us!');
    }
}
