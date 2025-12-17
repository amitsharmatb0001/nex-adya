<?php

namespace App\Services\Fraud;

use App\Models\User;
use App\Models\Order;
use App\Models\FraudLog;

class FraudRulesEngine
{
    /**
     * Run static rule-based checks.
     */
    public function evaluate(User $user, array $context): array
    {
        $alerts = [];

        // 1. High-value order
        if (($context['order_total'] ?? 0) > 100000) { // > ₹1000
            $alerts[] = "High value order: " . ($context['order_total'] / 100);
        }

        // 2. Too many failed login attempts
        if (($context['failed_logins'] ?? 0) > 5) {
            $alerts[] = "Multiple failed logins from IP {$context['ip']}";
        }

        // 3. Geo-IP mismatch (login from different city than last)
        if (!empty($user->last_city) && $user->last_city !== ($context['city'] ?? '')) {
            $alerts[] = "Geo mismatch: {$user->last_city} → {$context['city']}";
        }

        // 4. Device mismatch
        if (!empty($user->last_device_hash) && $user->last_device_hash !== ($context['device_hash'] ?? '')) {
            $alerts[] = "Device mismatch detected.";
        }

        return $alerts;
    }
}
