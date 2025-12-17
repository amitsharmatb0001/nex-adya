<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderPlacedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    /**
     * Keep this small: pass only scalars/ids to the job.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
        $this->onQueue('orders'); // optional: routes these jobs to "orders" queue
    }

    /**
     * Backoff between retries (seconds). Tweak as needed.
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    /**
     * Optional: rate limit to avoid spamming downstream providers.
     */
    public function middleware(): array
    {
        return [new RateLimited('order-events')];
    }

    public function handle(): void
    {
        // For now, just log. Later: send mail, push, webhook, etc.
        Log::info('OrderPlaced job running', ['order_id' => $this->orderId]);

        // Example future hooks:
        // - Mail::to($user)->send(new OrderPlacedMail($order));
        // - Http::post($partnerWebhook, [...]);
        // - event(new OrderPlacedBroadcast(...));
    }
}
