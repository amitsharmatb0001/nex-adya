<?php

namespace App\Listeners;

use App\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * SendOrderNotifications
 *
 * Listens to OrderStatusUpdated and:
 * - Notifies the user (in-app / email / push)
 * - Notifies the restaurant owners (in-app / push)
 *
 * NOTE: This listener is synchronous if not queued. Implement ShouldQueue
 * and use queue workers for production (we recommend queue).
 */
class SendOrderNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderStatusUpdated $event)
    {
        try {
            /** @var Order $order */
            $order = $event->order;

            $payload = [
                'order_id'  => $order->id,
                'old_status'=> $event->oldStatus,
                'new_status'=> $event->newStatus,
                'total'     => $order->total,
            ];

            // Notify user
            if ($order->user) {
                Notification::send($order->user, new \App\Notifications\OrderStatusChangedNotification($payload));
            }

            // Notify restaurant owners (all active owners for restaurant)
            $owners = $order->restaurant?->owners()?->where('is_active', true)->get();
            if ($owners && $owners->count()) {
                Notification::send($owners, new \App\Notifications\OrderStatusChangedNotification($payload, 'restaurant'));
            }

            Log::debug("OrderStatus notifications sent for order {$order->id}", ['payload' => $payload]);
        } catch (\Throwable $e) {
            Log::error('SendOrderNotifications failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // swallow error - do not break business flow
        }
    }
}
