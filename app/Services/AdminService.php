<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminService
{
    // Very small service to hold admin helper logic
    public function overview(): array
    {
        // return counts for dashboard (example)
        return [
            'users'       => \App\Models\User::count(),
            'restaurants' => \App\Models\Restaurant::count(),
            'orders_today' => \App\Models\Order::whereDate('created_at', now()->toDateString())->count(),
        ];
    }

    public function logAdminAction(User $admin, string $action, array $meta = [])
    {
        // Dispatch event for persistence + audit
        event(new \App\Events\AdminActionLogged($admin->id, $action, $meta));
        Log::info("Admin action: {$action}", array_merge(['admin_id'=>$admin->id], $meta));
    }
}
