<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminActionLogged
{
    use Dispatchable, SerializesModels;

    public $adminId;
    public $action;
    public $meta;

    public function __construct(int $adminId, string $action, array $meta = [])
    {
        $this->adminId = $adminId;
        $this->action = $action;
        $this->meta = $meta;
    }
}
