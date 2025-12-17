<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryPartner>
 */
class DeliveryPartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'license_number' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{2}[0-9]{4}[0-9]{7}'),
            'vehicle_type' => $this->faker->randomElement(['bike', 'car', 'scooter']),
            'vehicle_number' => $this->faker->regexify('[A-Z]{2}[0-9]{2}[A-Z]{1,2}[0-9]{4}'),
            'phone' => $this->faker->unique()->phoneNumber(),
            'documents' => [
                'license' => $this->faker->url(),
                'insurance' => $this->faker->url(),
                'rc' => $this->faker->url(),
            ],
            'rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_deliveries' => $this->faker->numberBetween(0, 1000),
            'is_active' => $this->faker->boolean(90),
            'is_available' => $this->faker->boolean(70),
            'last_active_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'current_location' => [
                'lat' => $this->faker->latitude(12.8, 13.2),
                'lng' => $this->faker->longitude(77.5, 77.8),
            ],
        ];
    }
}
