<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class DeliveryAssignmentPolicy
{
    public function actOnOrder(User $user, Order $order): bool
    {
        // User must be a delivery partner assigned to this order
        return $user->role === 'delivery'
            && $order->delivery_partner_id === $user->deliveryPartner->id;
    }
}
