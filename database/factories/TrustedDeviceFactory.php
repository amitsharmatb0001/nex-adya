<?php

namespace Database\Factories;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    protected $model = TrustedDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rawId = Str::random(12);
        $rawSecret = Str::random(64);
        $rawToken = "{$rawId}.{$rawSecret}";
        $hash = hash_hmac('sha256', $rawSecret, config('app.key'));

        return [
            'user_id' => User::factory(),
            'device_name' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']) . ' on ' . $this->faker->randomElement(['Windows', 'macOS', 'Linux']),
            'token_hash' => $hash,
            'raw_token_id' => $rawId,
            'expires_at' => Carbon::now()->addDays(365),
            'last_used_at' => Carbon::now()->subDays(rand(1, 30)),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the device is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
