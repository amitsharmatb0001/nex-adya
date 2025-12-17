<?php

namespace App\Services;

use App\Models\{
    Order,
    OrderItem,
    MenuItem,
    Address,
    FraudLog,
    StatusChangeLog
};
use App\Events\OrderStatusUpdated;
use App\Jobs\GenerateInvoiceJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * OrderService
 *
 * Centralized domain service for all order-related business logic.
 * Controllers should only:
 *  - validate input
 *  - authorize access
 *  - call this service
 *
 * Why this exists:
 *  - Prevent fat controllers
 *  - Enforce consistent rules
 *  - Keep side-effects (events, logs, jobs) in one place
 */
class OrderService
{
    /**
     * Create an order safely.
     * - Validates address ownership
     * - Snapshots menu prices
     * - Computes total server-side
     * - Runs inside a transaction to avoid partial writes
     */
    public function create(int $userId, array $payload): Order
    {
        return DB::transaction(function () use ($userId, $payload) {
            $restaurantId = (int) ($payload['restaurant_id'] ?? 0);
            $addressId    = (int) ($payload['address_id'] ?? 0);
            $itemsInput   = $payload['items'] ?? [];

            $address = Address::find($addressId);
            if (! $address) {
                abort(404, 'Address not found');
            }

            if ((int) $address->user_id !== $userId) {
                abort(403, 'Address does not belong to you');
            }

            $ids = array_values(array_unique(Arr::pluck($itemsInput, 'menu_item_id')));
            if (empty($ids)) {
                abort(422, 'No items provided.');
            }

            $dbItems = MenuItem::query()
                ->whereIn('id', $ids)
                ->where('is_available', true)
                ->with('menu:id,restaurant_id')
                ->get(['id', 'price', 'menu_id']);

            if ($dbItems->count() !== count($ids)) {
                abort(422, 'Some items are unavailable.');
            }

            foreach ($dbItems as $item) {
                if ((int) $item->menu->restaurant_id !== $restaurantId) {
                    abort(422, 'Items must belong to the selected restaurant.');
                }
            }

            $indexed = $dbItems->keyBy('id');
            $total = 0;

            foreach ($itemsInput as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $total += $indexed[$it['menu_item_id']]->price * $qty;
            }

            $order = Order::create([
                'user_id'        => $userId,
                'restaurant_id'  => $restaurantId,
                'address_id'     => $address->id,
                'total'          => $total,
                'status'         => Order::STATUS_PENDING ?? 'pending',
                'notes'          => $payload['notes'] ?? null,
                'voice_note_path'=> $payload['voice_note_path'] ?? null,
            ]);

            $rows = [];
            foreach ($itemsInput as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $mi  = $indexed[$it['menu_item_id']];

                $rows[] = [
                    'order_id'     => $order->id,
                    'menu_item_id' => $mi->id,
                    'quantity'     => $qty,
                    'price'        => $mi->price,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            OrderItem::insert($rows);

            Log::info('Order created', [
                'order_id' => $order->id,
                'user_id'  => $userId,
                'total'    => $total,
            ]);

            return $order->load(['items.menuItem']);
        });
    }

    /**
     * Update order status with:
     * - strict transition validation
     * - row-level locking
     * - audit logging
     * - events and async jobs
     *
     * THIS is the single source of truth for status changes.
     */
    public function updateStatus(Order $order, string $newStatus): array
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $order = Order::lockForUpdate()->find($order->id);
            $oldStatus = $order->status;

            if (! $this->isTransitionAllowed($oldStatus, $newStatus)) {
                return [
                    'success' => false,
                    'message' => "Invalid status transition from {$oldStatus} to {$newStatus}",
                ];
            }

            // 1. Persist state change
            $order->status = $newStatus;
            $order->save();

            // 2. AUDIT LOG (critical, must be inside transaction)
            StatusChangeLog::create([
                'entity_type' => 'order',
                'entity_id'   => $order->id,
                'from_status' => $oldStatus,
                'to_status'   => $newStatus,
                'changed_by'  => auth()->id(),
                'role'        => optional(auth()->user())->role,
            ]);

            // 3. Fire domain event
            event(new OrderStatusUpdated($order, $oldStatus, $newStatus));

            // 4. Trigger async side-effects
            if ($newStatus === (Order::STATUS_DELIVERED ?? 'delivered')) {
                GenerateInvoiceJob::dispatch($order->id);
            }

            Log::info('Order status updated', [
                'order_id' => $order->id,
                'from'     => $oldStatus,
                'to'       => $newStatus,
            ]);

            return [
                'success' => true,
                'data'    => $order,
            ];
        });
    }

    /**
     * Defines allowed order state transitions.
     * Prevents illegal jumps like pending → delivered.
     */
    private function isTransitionAllowed(string $from, string $to): bool
    {
        $map = [
            'pending'          => ['confirmed', 'cancelled'],
            'confirmed'        => ['preparing', 'cancelled'],
            'preparing'        => ['ready', 'cancelled'],
            'ready'            => ['out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['delivered', 'cancelled'],
            'delivered'        => [],
            'cancelled'        => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }
}
