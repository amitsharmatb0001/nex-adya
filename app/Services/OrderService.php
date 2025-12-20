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
 * ✅ P1 FIX #9: Added eager loading in listForUser() method
 */
class OrderService
{
    /**
     * ✅ P1 FIX #9: List orders for user with eager loading (NO N+1 queries)
     * 
     * This method was missing eager loading, causing N+1 queries.
     * Now loads: restaurant, items, menuItem, address in ONE query.
     */
    public function listForUser(int $userId, string $scope, int $size): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->with([
                'restaurant:id,name,slug,city,image,eta_minutes',
                'items.menuItem:id,name,price,image_url',
                'address:id,line1,city,pincode'
            ])
            ->orderByDesc('id');

        // Filter by scope
        if ($scope === 'active') {
            $query->whereIn('status', ['pending','confirmed','preparing','out_for_delivery']);
        } elseif ($scope === 'delivered') {
            $query->where('status', 'delivered');
        } elseif ($scope === 'cancelled') {
            $query->where('status', 'cancelled');
        }
        // else 'all' - no filter

        return $query->paginate($size);
    }

    /**
     * Get single order for user with eager loading
     */
    public function getForUser(int $userId, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('id', $orderId)
            ->with([
                'restaurant:id,name,slug,city,image,eta_minutes,address,phone',
                'address',
                'items.menuItem:id,name,price,veg,rating,image_url'
            ])
            ->firstOrFail();
    }

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
                    'voice_note_path' => $it['voice_note_path'] ?? null,
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
     * Update order items (for pending orders only)
     */
    public function updateItems(int $userId, int $orderId, array $payload): Order
    {
        return DB::transaction(function () use ($userId, $orderId, $payload) {
            $order = Order::lockForUpdate()
                ->where('user_id', $userId)
                ->findOrFail($orderId);

            if ($order->status !== (Order::STATUS_PENDING ?? 'pending')) {
                abort(422, 'Cannot modify order after it has been confirmed.');
            }

            $itemsInput = $payload['items'] ?? [];
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
                if ((int) $item->menu->restaurant_id !== (int) $order->restaurant_id) {
                    abort(422, 'Items must belong to the original restaurant.');
                }
            }

            // Delete existing items
            $order->items()->delete();

            // Recompute total and insert new items
            $indexed = $dbItems->keyBy('id');
            $total = 0;
            $rows = [];

            foreach ($itemsInput as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $mi  = $indexed[$it['menu_item_id']];
                $total += $mi->price * $qty;

                $rows[] = [
                    'order_id'     => $order->id,
                    'menu_item_id' => $mi->id,
                    'quantity'     => $qty,
                    'price'        => $mi->price,
                    'voice_note_path' => $it['voice_note_path'] ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            OrderItem::insert($rows);

            $order->total = $total;
            $order->save();

            return $order->load(['items.menuItem']);
        });
    }

    /**
     * Cancel order for user
     */
    public function cancelForUser(int $userId, int $orderId): Order
    {
        $order = Order::where('user_id', $userId)->findOrFail($orderId);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            abort(422, 'Cannot cancel order in current status');
        }

        $order->status = Order::STATUS_CANCELLED ?? 'cancelled';
        $order->save();

        event(new OrderStatusUpdated($order, $order->getOriginal('status'), $order->status));

        return $order;
    }

    /**
     * Update order status with:
     * - strict transition validation
     * - row-level locking
     * - audit logging
     * - events and async jobs
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

            $order->status = $newStatus;
            $order->save();

            StatusChangeLog::create([
                'entity_type' => 'order',
                'entity_id'   => $order->id,
                'from_status' => $oldStatus,
                'to_status'   => $newStatus,
                'changed_by'  => auth()->id(),
                'role'        => optional(auth()->user())->role,
            ]);

            event(new OrderStatusUpdated($order, $oldStatus, $newStatus));

            if ($newStatus === (Order::STATUS_DELIVERED ?? 'delivered')) {
                GenerateInvoiceJob::dispatch($order->id);
            }

            return [
                'success' => true,
                'data' => $order->fresh(),
            ];
        });
    }

    /**
     * Validate status transitions
     */
    protected function isTransitionAllowed(string $from, string $to): bool
    {
        $allowed = [
            'pending'           => ['confirmed', 'cancelled'],
            'confirmed'         => ['preparing', 'cancelled'],
            'preparing'         => ['ready', 'cancelled'],
            'ready'             => ['out_for_delivery'],
            'out_for_delivery'  => ['delivered', 'cancelled'],
            'delivered'         => ['refunded'],
            'cancelled'         => [],
            'refunded'          => [],
        ];

        return in_array($to, $allowed[$from] ?? []);
    }
}