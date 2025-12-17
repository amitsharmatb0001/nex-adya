<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Review;

class ReviewPolicy
{
    /**
     * Determine if the given review can be viewed by the user.
     * Anyone can view reviews (public information).
     */
    public function view(?User $user, Review $review): bool
    {
        return true; // Reviews are public
    }

    /**
     * Determine if the given review can be updated by the user.
     */
    public function update(?User $user, ?Review $review): bool
    {
        if (!$review || !$user) return false;
        return $user->id === $review->user_id || $user->role === 'admin';
    }

    /**
     * Determine if the user can delete the review.
     * Users can delete their own reviews, admins can delete any review.
     */
    public function delete(?User $user, ?Review $review): bool
    {
        if (!$review || !$user) return false;
        return $user->id === $review->user_id || $user->role === 'admin';
    }

    /**
     * Determine if the user can create a review.
     * Optionally check verified purchase in controller or a service.
     */
    public function create(User $user): bool
    {
        return $user !== null;
    }
}
