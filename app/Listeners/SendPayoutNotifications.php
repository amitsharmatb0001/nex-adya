<?php

namespace App\Listeners;

use App\Events\PayoutStatusChanged;
use App\Notifications\PayoutStatusNotification;
use App\Mail\PayoutStatusMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPayoutNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PayoutStatusChanged $event): void
    {
        $payout = $event->payout;
        $old = $event->oldStatus;
        $new = $event->newStatus;

        // 1) Notify restaurant owner(s): we use restaurant->owners or requester->user
        $owner = $payout->requester ?? null;

        if ($owner) {
            // Laravel notification (mail + database + broadcast)
            $owner->notify(new PayoutStatusNotification($payout, $old, $new));

            // Also send an explicit mail Mailable (optional/duplicate if you want different layout)
            try {
                Mail::to($owner->email)->queue(new PayoutStatusMail($payout, $old, $new));
            } catch (\Throwable $e) {
                \Log::error('PayoutStatusMail failed: ' . $e->getMessage(), ['payout_id' => $payout->id]);
            }

            // 2) Push (via your PushService)
            try {
                app()->make(\App\Services\PushService::class)->sendToUser($owner->id, [
                    'title' => "Payout #{$payout->id} — {$new}",
                    'body'  => "₹" . number_format($payout->amount / 100, 2) . " — {$new}",
                    'data'  => ['payout_id' => $payout->id],
                ]);
            } catch (\Throwable $e) {
                \Log::error('PushService failed', ['err' => $e->getMessage()]);
            }
        }

        // 3) Admin ops: optionally notify ops Slack/email (not implemented)
    }
}
