<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Restaurant;

/** @extends Factory<\App\Models\Restaurant> */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        $cities   = ['Delhi', 'Mumbai', 'Bangalore', 'Hyderabad'];
        $cuisines = ['Indian','Italian','Chinese','Mexican','Japanese','American','Thai','Mediterranean','Cafe','Desserts'];

        return [
            'name'        => $this->faker->company(),
            'slug'        => $this->faker->unique()->slug(),
            'image'       => null, // you can point to /images/... later
            'city'        => $this->faker->randomElement($cities),
            'cuisine'     => $this->faker->randomElement($cuisines),
            'eta_minutes' => $this->faker->numberBetween(15, 50),
            'rating'      => $this->faker->randomFloat(1, 3.5, 5.0), // e.g., 4.7
            'is_active'   => true,
        ];
    }

    /**
     * Handy state for inactive restaurants (won’t show in list).
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
