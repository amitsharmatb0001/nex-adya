<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    public function before(User $user)
    {
        return $user->role === 'admin'; // only admins pass
    }

    public function approveRestaurantOwner(User $user) { return true; }
    public function rejectRestaurantOwner(User $user) { return true; }
    public function deleteReview(User $user) { return true; }
    public function approvePayout(User $user) { return true; }
    public function rejectPayout(User $user) { return true; }
}
