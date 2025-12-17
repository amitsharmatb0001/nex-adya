<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrderAcceptedNotification extends Notification
{
    use Queueable;

    protected $order;

    public function __construct($order) 
    { 
        $this->order = $order; 
    }

    public function via($notifiable)
    {
        return ['database', 'fcm'];
    }

    public function toArray($notifiable)
    {
        return [
            'order_id' => $this->order->id,
            'type' => 'order_accepted',
            'title' => 'Order Accepted',
            'message' => 'Your order has been accepted by the restaurant.',
            'data' => [
                'order_id' => $this->order->id,
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => $this->order->total,
                'status' => $this->order->status,
            ]
        ];
    }

    public function toFcm($notifiable)
    {
        return [
            'notification' => [
                'title' => 'Order Accepted',
                'body' => "Your order #{$this->order->id} has been accepted by {$this->order->restaurant->name}",
                'icon' => 'ic_notification',
                'sound' => 'default',
            ],
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_accepted',
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => (string) $this->order->total,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'priority' => 'high'
        ];
    }
}
