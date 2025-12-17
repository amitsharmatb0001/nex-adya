<?php

namespace Database\Factories;

use App\Models\{Review, RestaurantOwner};
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewReplyFactory extends Factory
{
    protected $model = ReviewReply::class;

    public function definition()
    {
        return [
            'review_id' => Review::factory(),
            'owner_id'  => RestaurantOwner::factory(),
            'reply'     => $this->faker->sentence(12),
        ];
    }
}
