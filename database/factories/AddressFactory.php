<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->optional()->randomElement(['Home', 'Office', 'Other']),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional()->secondaryAddress(),
            'landmark' => $this->faker->optional()->company(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'pincode' => $this->faker->postcode(),
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'is_default' => false,
        ];
    }

    public function default()
    {
        return $this->state(fn() => ['is_default' => true]);
    }
}
