<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail']; // later add Slack, SMS, etc.
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Order Received - #{$this->order->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new order has been placed at your restaurant.")
            ->line("Total: ₹" . number_format($this->order->total / 100, 2))
            ->action('View Order', url("/dashboard/orders/{$this->order->id}"))
            ->line('Keep your kitchen ready!');
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
                'total' => (int) $this->order->total,
            'created_at' => $this->order->created_at->toIso8601String(),
            'restaurant' => [
                'id' => $this->order->restaurant->id,
                'name' => $this->order->restaurant->name,
                'slug' => $this->order->restaurant->slug,
            ],
            'user' => [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
                'email' => $this->order->user->email,
            ],
            'items' => $this->order->items->map(function ($item) {
                return [
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menuItem->name,
                    'qty' => $item->quantity,
                    'price' => $item->price,
                    'veg' => $item->menuItem->veg,
                    'rating' => $item->menuItem->rating,
                    'image_url' => $item->menuItem->image_url,
                ];
            }),
        ];
    }
}
