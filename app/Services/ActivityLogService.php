<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    /**
     * Log an activity to DB and debug log.
     * $payload accepts array or scalar.
     */
    public static function log(string $event, array $meta = [], ?int $userId = null, ?string $sensitive = null): void
    {
        try {
            $data = [
                'user_id' => $userId ?? ($meta['user_id'] ?? auth()->id()),
                'event'  => $event,
                'ip'      => $meta['ip'] ?? request()->ip(),
                'user_agent' => $meta['user_agent'] ?? request()->userAgent(),
                'actor_type' => $meta['actor_type'] ?? null,
                'actor_id'   => $meta['actor_id'] ?? null,
                'meta' => $meta,
                'sensitive' => $sensitive, // For encrypted sensitive data
            ];
            
            // Store in database using Eloquent model
            ActivityLog::create($data);
            
            // Also log to file for debugging (keeping old behavior)
            Log::info("Activity: {$event}", $data);
            
        } catch (\Throwable $e) {
            // Don't let logging failures affect user flow; fallback to debug log
            Log::error("ActivityLog write failed: {$event} : {$e->getMessage()}", ['meta' => $meta]);
        }
    }

    /**
     * Log payout-specific activities
     */
    public static function logPayoutActivity(string $event, int $payoutId, array $additionalContext = []): void
    {
        self::log($event, array_merge([
            'payout_id' => $payoutId,
            'payout_event' => $event,
        ], $additionalContext));
    }

    /**
     * Log user activities
     */
    public static function logUserActivity(string $event, int $targetUserId, array $additionalContext = []): void
    {
        self::log($event, array_merge([
            'target_user_id' => $targetUserId,
            'user_event' => $event,
        ], $additionalContext));
    }

    /**
     * Log sensitive activities with encryption
     */
    public static function logSensitive(string $event, array $meta = [], ?string $sensitiveData = null, ?int $userId = null): void
    {
        // Encrypt sensitive data if provided
        $encryptedSensitive = $sensitiveData ? encrypt($sensitiveData) : null;
        
        self::log($event, $meta, $userId, $encryptedSensitive);
    }
}