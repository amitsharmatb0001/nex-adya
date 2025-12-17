<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        $restaurant = Restaurant::factory()->create();
        return [
            'user_id'       => User::factory(),
            'restaurant_id' => $restaurant->id,
            'address_id'    => null, // set explicitly in tests if needed
            'payment_id'    => null,
            'total'         => $this->faker->numberBetween(100, 5000) * 100, // in paise
            'status'        => $this->faker->randomElement(['pending','confirmed','preparing','out_for_delivery','delivered','cancelled']),
            'notes'         => $this->faker->optional()->sentence(),
            'voice_note_path'=> null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

    public function forRestaurant($restaurantId)
    {
        return $this->state(fn() => ['restaurant_id' => $restaurantId]);
    }

    public function pending()
    {
        return $this->state(fn() => ['status' => 'pending']);
    }

    public function delivered()
    {
        return $this->state(fn() => ['status' => 'delivered']);
    }
}
