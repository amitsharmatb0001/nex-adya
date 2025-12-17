<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPickedUpNotification extends Notification
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
            'type' => 'order_out_for_delivery',
            'title' => 'Order Out for Delivery',
            'message' => 'Your order is out for delivery.',
            'data' => [
                'order_id' => $this->order->id,
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => $this->order->total,
                'status' => $this->order->status,
                'delivery_partner' => $this->order->deliveryPartner->user->name ?? 'Delivery Partner',
            ]
        ];
    }

    public function toFcm($notifiable)
    {
        return [
            'notification' => [
                'title' => 'Order Out for Delivery',
                'body' => "Your order #{$this->order->id} is on its way!",
                'icon' => 'ic_delivery',
                'sound' => 'default',
            ],
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_out_for_delivery',
                'restaurant_name' => $this->order->restaurant->name ?? 'Restaurant',
                'total' => (string) $this->order->total,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'priority' => 'high'
        ];
    }
}
