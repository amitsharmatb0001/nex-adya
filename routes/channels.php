<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('order.{orderId}', function ($user, int $orderId) {
    $order = Order::find($orderId);
    if (!$order) {
        return false;
    }
    return (int) $user->id === (int) $order->user_id;
});


