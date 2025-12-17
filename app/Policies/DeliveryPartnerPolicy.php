<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DeliveryPartner;

class DeliveryPartnerPolicy
{
    /**
     * Determine if the user can view any delivery partners.
     */
    public function viewAny(?User $user): bool
    {
        return $user && in_array($user->role, ['admin', 'restaurant']);
    }

    /**
     * Determine if the user can view the delivery partner.
     */
    public function view(?User $user, ?DeliveryPartner $deliveryPartner): bool
    {
        if (!$user || !$deliveryPartner) {
            return false;
        }

        // Admins can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Delivery partners can view their own profile
        if ($user->isDelivery() && $deliveryPartner->user_id === $user->id) {
            return true;
        }

        // Restaurant owners can view delivery partners
        if ($user->isRestaurant()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create delivery partners.
     */
    public function create(?User $user): bool
    {
        return $user && $user->isAdmin();
    }

    /**
     * Determine if the user can update the delivery partner.
     */
    public function update(?User $user, ?DeliveryPartner $deliveryPartner): bool
    {
        if (!$user || !$deliveryPartner) {
            return false;
        }

        // Admins can update all
        if ($user->isAdmin()) {
            return true;
        }

        // Delivery partners can update their own profile
        if ($user->isDelivery() && $deliveryPartner->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the delivery partner.
     */
    public function delete(?User $user, ?DeliveryPartner $deliveryPartner): bool
    {
        return $user && $user->isAdmin();
    }

    /**
     * Determine if the user can assign orders to delivery partners.
     */
    public function assignOrder(?User $user): bool
    {
        return $user && in_array($user->role, ['admin', 'restaurant']);
    }

    /**
     * Determine if the user can update their availability.
     */
    public function updateAvailability(?User $user, ?DeliveryPartner $deliveryPartner): bool
    {
        if (!$user || !$deliveryPartner) {
            return false;
        }

        // Only delivery partners can update their own availability
        return $user->isDelivery() && $deliveryPartner->user_id === $user->id;
    }

    /**
     * Determine if the user can update their location.
     */
    public function updateLocation(?User $user, ?DeliveryPartner $deliveryPartner): bool
    {
        if (!$user || !$deliveryPartner) {
            return false;
        }

        // Only delivery partners can update their own location
        return $user->isDelivery() && $deliveryPartner->user_id === $user->id;
    }
}
