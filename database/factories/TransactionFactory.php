<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{Payout, Restaurant, User};

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payout_id' => Payout::factory(),
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['payout', 'order', 'refund']),
            'amount' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'INR',
            'reference' => $this->faker->optional()->uuid(),
            'meta' => [
                'provider' => $this->faker->optional()->randomElement(['razorpay', 'stripe', 'payu']),
                'gateway' => $this->faker->optional()->word()
            ]
        ];
    }

    public function payout(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payout',
            'amount' => $this->faker->numberBetween(10000, 500000),
        ]);
    }

    public function order(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'order',
            'amount' => $this->faker->numberBetween(1000, 10000),
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'refund',
            'amount' => $this->faker->numberBetween(100, 5000),
        ]);
    }
}