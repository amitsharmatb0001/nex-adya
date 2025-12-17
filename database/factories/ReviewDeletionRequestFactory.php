<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReviewDeletionRequest>
 */
class ReviewDeletionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => \App\Models\Review::factory(),
            'owner_id' => \App\Models\RestaurantOwner::factory(),
            'reason' => $this->faker->sentence(),
            'evidence' => $this->faker->optional()->paragraph(),
            'status' => 'pending',
            'admin_id' => null,
            'admin_notes' => null,
        ];
    }
}
