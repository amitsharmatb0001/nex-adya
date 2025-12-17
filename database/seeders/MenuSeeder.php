<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Restaurant;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = Restaurant::all();
        $menuTitles = ['Main Course', 'Appetizers', 'Quick Bites', 'Chef Specials', 'Beverages'];

        foreach ($restaurants as $restaurant) {
            // Create 2 to 3 menus for each restaurant
            for ($i = 0; $i < rand(2, 3); $i++) {
                Menu::create([
                    'restaurant_id' => $restaurant->id,
                    'title' => $menuTitles[array_rand($menuTitles)],
                    'description' => 'A selection of our finest ' . strtolower($menuTitles[array_rand($menuTitles)]) . '.',
                ]);
            }
        }
    }
}