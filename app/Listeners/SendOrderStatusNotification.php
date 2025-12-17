<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        // Notify user (email/push)
        $order->user->notify(new OrderStatusChangedNotification($order, $event->newStatus));

        // Optional: notify restaurant staff app
        $order->restaurant->notify(new RestaurantOrderNotification($order, $event->newStatus));
    }
}
