<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\User;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'requested_by' => User::factory(),
            'external_reference' => $this->faker->optional()->uuid(),
            'amount' => $this->faker->numberBetween(10000, 500000), // ₹100 to ₹5000
            'currency' => 'INR',
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'processing', 'paid', 'failed']),
            'notes' => $this->faker->optional()->sentence(),
            'approved_by' => $this->faker->optional()->randomElement(User::pluck('id')->toArray()),
            'approved_at' => $this->faker->optional()->dateTime(),
            'paid_at' => $this->faker->optional()->dateTime(),
            'payment_provider' => $this->faker->optional()->randomElement(['razorpay', 'stripe', 'payu']),
            'provider_response' => $this->faker->optional()->randomElements(['success', 'failed', 'pending']),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    public function pending()
    {
        return $this->state(fn() => [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function approved()
    {
        return $this->state(fn() => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected()
    {
        return $this->state(fn() => [
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function processing()
    {
        return $this->state(fn() => [
            'status' => 'processing',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function paid()
    {
        return $this->state(fn() => [
            'status' => 'paid',
            'approved_at' => now()->subDays(1),
            'approved_by' => User::factory(),
            'paid_at' => now(),
        ]);
    }

    public function failed()
    {
        return $this->state(fn() => [
            'status' => 'failed',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }
}