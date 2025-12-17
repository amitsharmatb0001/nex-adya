<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\MenuItem;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        $dish = $this->faker->randomElement([
            'Paneer Tikka', 'Chicken Biryani', 'Veg Hakka Noodles',
            'Margherita Pizza', 'Masala Dosa', 'Chole Bhature',
            'Butter Chicken', 'Dal Makhani', 'Schezwan Fried Rice',
            'Caesar Salad', 'Aloo Paratha', 'Idli Sambar'
        ]);

        // Price in paise: ₹99–₹499
        $pricePaise = $this->faker->numberBetween(9900, 49900);

        return [
            'menu_id'     => \App\Models\Menu::factory(),
            'name'        => $dish,
            'price'       => $pricePaise,
            'veg'         => $this->faker->boolean(65), // more veg in India
            'rating'      => $this->faker->randomFloat(2, 3.5, 5.0),
            'image_url'   => null,
            'is_available'=> $this->faker->boolean(92),
        ];
    }
}
