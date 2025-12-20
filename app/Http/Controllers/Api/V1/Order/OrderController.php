<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderItemsRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Services\OrderService;
use App\Jobs\SendOrderPlacedNotifications;
use App\Models\Order;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\FraudDetectionService;
use App\Services\ActivityLogService;

/**
 * OrderController (API V1)
 *
 * Thin controller: validate, authorize, then call service.
 * Handles multipart/form-data voice note upload handling with strict security validation.
 */
class OrderController extends Controller
{
    /**
     * GET /api/v1/orders
     */
    public function index(ListOrdersRequest $request, OrderService $orders)
    {
        $userId = $request->user()->id;
        $scope  = $request->scope();
        $size   = $request->size();

        $paginator = $orders->listForUser($userId, $scope, $size);

        // Shape response (light)
        return response()->json([
            'ok' => true,
            'data' => [
                'items' => collect($paginator->items())->map(function ($o) {
                    return [
                        'id' => $o->id,
                        'status' => $o->status,
                        'total' => $o->total,
                        'created_at' => $o->created_at?->toIso8601String(),
                        'restaurant' => [
                            'id' => $o->restaurant->id ?? null,
                            'name' => $o->restaurant->name ?? null,
                            'slug' => $o->restaurant->slug ?? null,
                            'city' => $o->restaurant->city ?? null,
                            'image' => $o->restaurant->image ?? null,
                            'eta_minutes' => $o->restaurant->eta_minutes ?? null,
                        ],
                    ];
                })->values(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'size' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'scope' => $scope,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/orders
     */
    public function store(StoreOrderRequest $request, OrderService $orders)
    {
        $fraudService = new FraudDetectionService();
        $fraudService->checkOrderSpike($request->user()->id);

        Log::debug('POST /api/v1/orders hit', [
            'user_id' => $request->user()?->id,
            'restaurant_id' => $request->input('restaurant_id'),
            'address_id' => $request->input('address_id'),
        ]);

        $user = $request->user();

        // optional idempotency
        $cacheKey = null;
        if ($request->hasHeader('Idempotency-Key')) {
            $key = $request->header('Idempotency-Key');
            $cacheKey = 'idem:' . sha1($user->id . '|' . $key);
            if (cache()->has($cacheKey)) {
                return response()->json(cache()->get($cacheKey));
            }
        }

        // ✅ SECURE - Store order-level voice note with validation
        $orderVoicePath = null;
        if ($request->hasFile('voice_note')) {
            $file = $request->file('voice_note');
            
            // Additional MIME type validation
            $allowedMimes = ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpga', 'audio/x-m4a'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid file type. Only audio files are allowed.'
                ], 422);
            }
            
            // Store in private disk (not publicly accessible)
            $orderVoicePath = $file->store('orders/voice', 'private');
        }

        $payload = $request->validated();
        $items   = $payload['items'];

        // ✅ SECURE - Store per-item voice note files with validation
        foreach ($items as $idx => $item) {
            $fileKey = "items.$idx.voice_note";
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                
                // Additional MIME type validation for item voice notes
                $allowedMimes = ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpga', 'audio/x-m4a'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return response()->json([
                        'ok' => false,
                        'error' => "Invalid file type for item $idx. Only audio files are allowed."
                    ], 422);
                }
                
                $items[$idx]['voice_note_path'] = $file->store('orders/voice', 'private');
            }
        }

        $payload['items'] = $items;
        $payload['voice_note_path'] = $orderVoicePath;

        // create order
        $order = $orders->create(userId: $user->id, payload: $payload);

        // dispatch notification job (async)
        SendOrderPlacedNotifications::dispatch($order->id);

        // activity log
        ActivityLogService::log('order_placed', ['order_id' => $order->id, 'user_id' => $user->id]);

        $response = [
            'ok' => true,
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'total' => $order->total,
                'items' => $order->items->map(fn($i) => [
                    'menu_item_id' => $i->menu_item_id,
                    'qty' => $i->quantity,
                    'price' => $i->price,
                    'voice_note_path' => $i->voice_note_path,
                ])->values(),
                'order_voice_note_path' => $order->voice_note_path,
            ],
        ];

        if ($cacheKey) {
            cache()->put($cacheKey, $response, now()->addMinutes(10));
        }

        return response()->json($response, 201);
    }

    /**
     * PATCH /api/v1/orders/{id}
     */
    public function update(UpdateOrderItemsRequest $request, $id, OrderService $orders)
    {
        $fraudService = new FraudDetectionService();
        $fraudService->checkOrderSpike($request->user()->id);

        Log::debug('PATCH /api/v1/orders hit', [
            'user_id' => $request->user()?->id,
            'order_id' => $id,
        ]);

        $user = $request->user();

        $orderVoicePath = null;
        if ($request->boolean('clear_order_voice_note')) {
            $orderVoicePath = ''; // sentinel to clear
        } elseif ($request->hasFile('voice_note')) {
            $file = $request->file('voice_note');
            
            // Additional MIME type validation
            $allowedMimes = ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpga', 'audio/x-m4a'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid file type. Only audio files are allowed.'
                ], 422);
            }
            
            $orderVoicePath = $file->store('orders/voice', 'private');
        }

        $payload = $request->validated();
        $items   = $payload['items'];

        foreach ($items as $idx => $item) {
            $fileKey = "items.$idx.voice_note";
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                
                // Additional MIME type validation
                $allowedMimes = ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpga', 'audio/x-m4a'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return response()->json([
                        'ok' => false,
                        'error' => "Invalid file type for item $idx. Only audio files are allowed."
                    ], 422);
                }
                
                $items[$idx]['voice_note_path'] = $file->store('orders/voice', 'private');
            }
        }

        $payload['items'] = $items;
        $payload['voice_note_path'] = $orderVoicePath;

        $order = $orders->updateItems(userId: $user->id, orderId: (int)$id, payload: $payload);

        ActivityLogService::log('order_updated', ['order_id' => $order->id, 'user_id' => $user->id]);

        return response()->json([
            'ok' => true,
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'total' => $order->total,
                'items' => $order->items->map(fn($i) => [
                    'menu_item_id' => $i->menu_item_id,
                    'qty' => $i->quantity,
                    'price' => $i->price,
                    'voice_note_path' => $i->voice_note_path,
                ])->values(),
                'order_voice_note_path' => $order->voice_note_path,
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/{id}
     */
    public function show($id, OrderService $orders)
    {
        $userId = request()->user()->id;
        $order = $orders->getForUser(userId: $userId, orderId: (int)$id);

        return response()->json(['ok' => true, 'data' => [
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'notes' => $order->notes,
            'voice_note_path' => $order->voice_note_path,
            'restaurant' => $order->getRelation('restaurant'),
            'address' => $order->getRelation('address'),
            'items' => $order->items->map(fn($i) => [
                'menu_item_id' => $i->menu_item_id,
                'name' => $i->menuItem->name ?? null,
                'qty' => $i->quantity,
                'price' => $i->price,
                'veg' => $i->menuItem->veg ?? null,
                'rating' => $i->menuItem->rating ?? null,
                'image_url' => $i->menuItem->image_url ?? null,
                'voice_note_path' => $i->voice_note_path,
            ])->values(),
        ]]);
    }

    /**
     * GET /api/v1/orders/{order}/voice-note
     * ✅ SECURE - Serve voice note from private storage with authorization
     */
    public function getVoiceNote(Order $order)
    {
        // Check authorization
        if (auth()->id() !== $order->user_id) {
            return response()->json([
                'ok' => false,
                'error' => 'Unauthorized'
            ], 403);
        }
        
        if (!$order->voice_note_path) {
            return response()->json([
                'ok' => false,
                'error' => 'No voice note found'
            ], 404);
        }
        
        // Serve file from private storage
        return Storage::disk('private')->download($order->voice_note_path);
    }

    /**
     * POST /api/v1/orders/{id}/cancel
     */
    public function cancel($id, OrderService $orders)
    {
        Log::debug('POST /api/v1/orders/{id}/cancel hit', [
            'user_id' => request()->user()?->id,
            'order_id' => $id,
        ]);

        $order = $orders->cancelForUser(userId: request()->user()->id, orderId: (int)$id);

        // broadcast (best-effort)
        try {
            event(new OrderStatusUpdated($order, 'pending', Order::STATUS_CANCELLED ?? 'cancelled'));
        } catch (\Throwable $e) {
            Log::error("Broadcast failed for order {$order->id}", ['error' => $e->getMessage()]);
        }

        ActivityLogService::log('order_cancelled', ['order_id' => $order->id, 'user_id' => request()->user()->id]);

        return response()->json(['ok' => true, 'data' => ['order_id' => $order->id, 'status' => $order->status]]);
    }

    /**
     * POST /api/v1/orders/{id}/update-status
     *
     * Used by restaurant owners and admins.
     */
    public function updateStatus(int $id, OrderService $orders)
    {
        try {
            $request = request();
            $data = $request->validate([
                'status' => 'required|string|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled'
            ]);

            $order = Order::findOrFail($id);
            $oldStatus = $order->status;

            $res = $orders->updateStatus($order, $data['status']);
            if (! $res['success']) {
                return response()->json(['ok' => false, 'error' => $res['message']], 422);
            }

            // invoice email when delivered (best-effort)
            if ($data['status'] === 'delivered') {
                try {
                    Mail::to($order->user->email)->queue(new OrderInvoiceMail($order));
                } catch (\Throwable $e) {
                    Log::error("Invoice email failed", ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            // broadcast
            try {
                event(new OrderStatusUpdated($order, $oldStatus, $data['status']));
            } catch (\Throwable $e) {
                Log::error("Broadcast failed for order {$order->id}", ['error' => $e->getMessage()]);
            }

            ActivityLogService::log('order_status_changed', ['order_id' => $order->id, 'from' => $oldStatus, 'to' => $data['status']]);

            return response()->json(['ok' => true, 'data' => $res['data']]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}