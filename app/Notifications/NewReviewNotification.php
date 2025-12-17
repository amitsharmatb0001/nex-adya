<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Review $review) {}

    public function via($notifiable)
    {
        return ['database']; // can add 'mail' or 'broadcast' later
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'review',
            'review_id'    => $this->review->id,
            'restaurant_id'=> $this->review->restaurant_id,
            'rating'       => $this->review->rating,
            'excerpt'      => substr($this->review->review, 0, 120),
            'user'         => [
                'id'   => $this->review->user->id,
                'name' => $this->review->user->name,
            ],
        ];
    }
}
