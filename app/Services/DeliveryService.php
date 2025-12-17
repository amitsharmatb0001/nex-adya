<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryPartner;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderPickedUpNotification;
use App\Notifications\OrderDeliveredNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * DeliveryService
 *
 * Controls delivery partner flows: accept -> pickup -> deliver
 * - Locks order row while updating
 * - Validates transitions
 * - Assigns delivery_partner_id on accept
 * - Records timestamps (picked_up_at, delivered_at)
 * - Emits OrderStatusUpdated event
 */
class DeliveryService
{
    public function updateStatus(Order $order, DeliveryPartner $partner, string $action): Order
    {
        // Map actions -> statuses (adjust to your domain terms)
        $map = [
            'accept' => 'accepted',
            'pickup' => 'out_for_delivery',
            'deliver'=> 'delivered',
        ];

        if (! isset($map[$action])) {
            throw new \InvalidArgumentException("Unknown delivery action: {$action}");
        }

        $newStatus = $map[$action];

        return DB::transaction(function () use ($order, $partner, $newStatus, $action) {

            // lock row for concurrent updates
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $oldStatus = $order->status;

            // Basic authorization: ensure partner is assigned to order (optional: more checks)
            if ($order->delivery_partner_id && (int)$order->delivery_partner_id !== (int)$partner->id) {
                abort(403, 'You are not assigned to this order.');
            }

            // Transition validation (implement your own rules or reuse isTransitionAllowed)
            if (! $this->isTransitionAllowed($oldStatus, $newStatus)) {
                abort(422, "Invalid status transition from {$oldStatus} to {$newStatus}");
            }

            // update timestamps for pickup/deliver
            $updates = ['status' => $newStatus];
            if ($newStatus === 'accepted') {
                $updates['accepted_at'] = $order->accepted_at ?: now();
                $updates['delivery_partner_id'] = $partner->id;
            } elseif ($newStatus === 'out_for_delivery') {
                $updates['picked_up_at'] = $order->picked_up_at ?: now();
                $updates['delivery_partner_id'] = $partner->id;
            } elseif ($newStatus === 'delivered') {
                $updates['delivered_at'] = $order->delivered_at ?: now();
                $updates['delivery_partner_id'] = $partner->id;
            }

            $order->update($updates);

            // Activity log for audit & fraud tools
            ActivityLogService::log('delivery_status_changed', [
                'order_id' => $order->id,
                'user_id' => $partner->user_id,
                'partner_id' => $partner->id,
                'from' => $oldStatus,
                'to' => $newStatus,
                'ip' => request()->ip(),
            ]);

            // Notifications:
            // - For accept/pickup: only app push (no email)
            // - For delivered: push + email
            // Use orders.last_notified_status to avoid duplicates

            $alreadyNotified = $order->last_notified_status === $newStatus;

            // Decide which notification to send
            if (! $alreadyNotified) {
                try {
                    if ($newStatus === 'accepted') {
                        // push to user + restaurant
                        Notification::send($order->user, new OrderAcceptedNotification($order));
                        Notification::send($order->restaurant, new OrderAcceptedNotification($order)); // restaurant push channel logic inside notification
                    } elseif ($newStatus === 'out_for_delivery') {
                        Notification::send($order->user, new OrderPickedUpNotification($order));
                        Notification::send($order->restaurant, new OrderPickedUpNotification($order));
                    } elseif ($newStatus === 'delivered') {
                        // push first
                        Notification::send($order->user, new OrderDeliveredNotification($order, pushOnly: false)); // second param false -> email included
                        Notification::send($order->restaurant, new OrderDeliveredNotification($order, pushOnly: true));
                        // queue invoice generation/email sending
                        \App\Jobs\GenerateInvoiceJob::dispatch($order->id);
                    }

                    // persist last_notified_status after successful notify
                    $order->last_notified_status = $newStatus;
                    $order->saveQuietly(); // small write, avoids triggering extra events
                } catch (\Throwable $e) {
                    // Log but do not abort the main flow (so status update still succeeds)
                    Log::error("Notification failed for order {$order->id} status {$newStatus}", ['error' => $e->getMessage()]);
                }
            } else {
                Log::debug("Notifications already sent for order {$order->id} status {$newStatus}");
            }

            // Fire domain event (for websockets or hooks)
            event(new \App\Events\OrderStatusUpdated($order, $oldStatus, $newStatus));

            return $order;
        });
    }

    /**
     * Your existing transition rules; keep in service to centralize logic.
     */
    private function isTransitionAllowed(string $from, string $to): bool
    {
        $map = [
            'pending'          => ['accepted','cancelled'],
            'accepted'         => ['out_for_delivery','cancelled'],
            'out_for_delivery' => ['delivered','cancelled'],
            'delivered'        => [],
            'cancelled'        => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }
}
