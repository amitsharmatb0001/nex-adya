<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ✅ P2 FIX #12: Async Activity Logging
 * 
 * This job writes activity logs asynchronously to avoid blocking API responses.
 * Retry logic ensures logs aren't lost on failure.
 */
class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $event,
        public array $data,
        public ?int $userId,
        public string $ip,
        public string $userAgent,
        public ?array $meta = null,
        public bool $sensitive = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ActivityLog::create([
                'event'      => $this->event,
                'data'       => json_encode($this->data),
                'user_id'    => $this->userId,
                'ip'         => $this->ip,
                'user_agent' => $this->userAgent,
                'meta'       => $this->meta ? json_encode($this->meta) : null,
                'sensitive'  => $this->sensitive,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log to Laravel logs but don't crash
            Log::error('Activity log creation failed', [
                'event' => $this->event,
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
            ]);
            
            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // After all retries failed, log to error tracking
        Log::error('Activity logging permanently failed', [
            'event' => $this->event,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Calculate backoff time for retries (exponential backoff).
     */
    public function backoff(): array
    {
        return [1, 5, 10]; // Retry after 1s, 5s, 10s
    }
}