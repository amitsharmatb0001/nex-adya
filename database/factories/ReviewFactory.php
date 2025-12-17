<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $restaurant = Restaurant::inRandomOrder()->first() ?? Restaurant::factory()->create();
        $menuItem = MenuItem::where('menu_id', $restaurant->menus()->inRandomOrder()->first()?->id)->first();

        return [
            'user_id'         => User::inRandomOrder()->first()?->id ?? User::factory(),
            'reviewable_id'   => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating'          => $this->faker->numberBetween(1, 5),
            'comment'         => $this->faker->paragraph,
        ];
    }

    public function forMenuItem()
    {
        return $this->state(function (array $attributes) {
            $menuItem = MenuItem::inRandomOrder()->first() ?? MenuItem::factory()->create();
            return [
                'reviewable_id'   => $menuItem->id,
                'reviewable_type' => MenuItem::class,
            ];
        });
    }
}
