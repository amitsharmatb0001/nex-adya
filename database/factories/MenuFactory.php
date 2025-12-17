<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Menu;

/** @extends Factory<Menu> */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        $titles = ['Main Menu', 'Quick Bites', 'Combos', 'Chef Specials'];
        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'title'       => $this->faker->randomElement($titles),
            'description' => $this->faker->optional()->sentence(8),
        ];
    }
}
