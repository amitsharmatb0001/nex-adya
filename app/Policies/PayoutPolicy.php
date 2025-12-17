<?php
namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    public function view(?User $user, ?Payout $payout): bool
    {
        if (!$user || !$payout) {
            return false;
        }
        return $user->role === 'admin' || $payout->requested_by === $user->id;
    }

    public function create(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return in_array($user->role, ['restaurant', 'admin']);
    }
}
