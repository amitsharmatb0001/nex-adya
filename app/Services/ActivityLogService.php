<?php

namespace App\Services;

use App\Jobs\LogActivityJob;

/**
 * ✅ P2 FIX #12: Async Activity Logging Service
 * 
 * REPLACE: app/Services/ActivityLogService.php
 * 
 * Changes:
 * - Dispatches LogActivityJob instead of synchronous DB write
 * - Uses afterResponse() to avoid blocking API responses
 * - Supports both queued and sync modes via environment variable
 */
class ActivityLogService
{
    /**
     * Log an activity event asynchronously.
     *
     * @param string $event Event name (e.g., 'login_success', 'order_placed')
     * @param array $data Event-specific data to log
     * @param array|null $meta Additional metadata (optional)
     * @param bool $sensitive Mark log as containing sensitive info (default: false)
     * @return void
     */
    public static function log(
        string $event,
        array $data,
        ?array $meta = null,
        bool $sensitive = false
    ): void {
        // Check if async logging is enabled (default: true)
        $async = config('logging.activity.async', true);

        if ($async) {
            // ✅ ASYNC: Queue the job (doesn't block response)
            dispatch(new LogActivityJob(
                event: $event,
                data: $data,
                userId: auth()->id(),
                ip: request()->ip() ?? '0.0.0.0',
                userAgent: request()->userAgent() ?? 'Unknown',
                meta: $meta,
                sensitive: $sensitive
            ))->afterResponse();
        } else {
            // ❌ SYNC: Direct database write (fallback for testing)
            \App\Models\ActivityLog::create([
                'event'      => $event,
                'data'       => json_encode($data),
                'user_id'    => auth()->id(),
                'ip'         => request()->ip() ?? '0.0.0.0',
                'user_agent' => request()->userAgent() ?? 'Unknown',
                'meta'       => $meta ? json_encode($meta) : null,
                'sensitive'  => $sensitive,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Log a sensitive activity event (passwords, 2FA, etc.)
     *
     * @param string $event Event name
     * @param array $data Event data (will be marked as sensitive)
     * @return void
     */
    public static function logSensitive(string $event, array $data): void
    {
        self::log($event, $data, null, true);
    }

    /**
     * Force synchronous logging (for critical events that must be logged immediately)
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return void
     */
    public static function logSync(string $event, array $data): void
    {
        \App\Models\ActivityLog::create([
            'event'      => $event,
            'data'       => json_encode($data),
            'user_id'    => auth()->id(),
            'ip'         => request()->ip() ?? '0.0.0.0',
            'user_agent' => request()->userAgent() ?? 'Unknown',
            'created_at' => now(),
        ]);
    }
}