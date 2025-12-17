<?php

namespace App\Services;

use App\Models\SecurityLog;
use Illuminate\Support\Facades\Cache;

class FraudDetectionService
{
    public function log($userId, $action, $reason, $metadata = [])
    {
        SecurityLog::create([
            'user_id'   => $userId,
            'action'    => $action,
            'reason'    => $reason,
            'ip_address'=> request()->ip(),
            'metadata'  => $metadata,
        ]);
    }

    public function checkLoginFailures($email)
    {
        $key = "login:failures:{$email}";
        $failures = Cache::increment($key);
        Cache::put($key, $failures, now()->addMinutes(15));

        if ($failures >= 5) {
            $this->log(null, 'login_lockout', 'Too many failed login attempts', ['email' => $email]);
            abort(429, 'Too many failed login attempts. Please try again later.');
        }
    }

    public function checkOrderSpike($userId)
    {
        $key = "orders:{$userId}:count";
        $count = Cache::increment($key);
        Cache::put($key, $count, now()->addMinutes(10));

        if ($count > 5) {
            $this->log($userId, 'order_spike', 'Too many orders in short time');
            abort(429, 'Order limit exceeded. Please wait a while.');
        }
    }

    public function checkPaymentFailures($userId)
    {
        $key = "payment:failures:{$userId}";
        $failures = Cache::increment($key);
        Cache::put($key, $failures, now()->addMinutes(60));

        if ($failures >= 3) {
            $this->log($userId, 'payment_block', 'Too many failed payments');
            abort(429, 'Payments temporarily blocked. Try again later.');
        }
    }

    public function recordFailedLogin($email, $ip)
    {
        $this->log(null, 'login_failure', 'Failed login attempt', [
            'email' => $email,
            'ip' => $ip
        ]);
    }
}
