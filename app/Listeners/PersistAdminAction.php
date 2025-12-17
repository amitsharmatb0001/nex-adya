<?php

namespace App\Listeners;

use App\Events\AdminActionLogged;
use App\Models\AdminActionLog; // you will create migration/model
use Illuminate\Contracts\Queue\ShouldQueue;

class PersistAdminAction implements ShouldQueue
{
    public function handle(AdminActionLogged $event)
    {
        AdminActionLog::create([
            'admin_user_id' => $event->adminId,
            'action' => $event->action,
            'meta' => json_encode($event->meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
