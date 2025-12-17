<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;

class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        if (Menu::query()->count() === 0) {
            $this->command->info('No menus found. Please run the RestaurantSeeder first to create restaurants and their menus.');
            return;
        }

        // --- All the menu items data remains the same ---
        // NOTE: Make sure this array contains at least 25 items for best results.
        $menuItemsData = [
            // --- Indian Cuisine ---
            ['name' => 'Paneer Tikka', 'price' => 28000, 'veg' => true],
            ['name' => 'Samosa Chaat', 'price' => 12000, 'veg' => true],
            ['name' => 'Hara Bhara Kebab', 'price' => 24000, 'veg' => true],
            ['name' => 'Chicken 65', 'price' => 32000, 'veg' => false],
            ['name' => 'Dal Makhani', 'price' => 25000, 'veg' => true],
            ['name' => 'Palak Paneer', 'price' => 26000, 'veg' => true],
            ['name' => 'Malai Kofta', 'price' => 27000, 'veg' => true],
            ['name' => 'Chole Bhature', 'price' => 18000, 'veg' => true],
            ['name' => 'Butter Chicken', 'price' => 45000, 'veg' => false],
            ['name' => 'Rogan Josh (Mutton)', 'price' => 48000, 'veg' => false],
            ['name' => 'Fish Amritsari', 'price' => 42000, 'veg' => false],
            ['name' => 'Chicken Korma', 'price' => 43000, 'veg' => false],
            ['name' => 'Chicken Biryani', 'price' => 35000, 'veg' => false],
            ['name' => 'Vegetable Pulao', 'price' => 22000, 'veg' => true],
            ['name' => 'Jeera Rice', 'price' => 18000, 'veg' => true],
            ['name' => 'Butter Naan', 'price' => 6000, 'veg' => true],
            ['name' => 'Garlic Naan', 'price' => 7000, 'veg' => true],
            ['name' => 'Tandoori Roti', 'price' => 4000, 'veg' => true],
            // --- South Indian ---
            ['name' => 'Masala Dosa', 'price' => 15000, 'veg' => true],
            ['name' => 'Idli Sambhar', 'price' => 10000, 'veg' => true],
            ['name' => 'Vada Sambhar', 'price' => 11000, 'veg' => true],
            ['name' => 'Rava Onion Dosa', 'price' => 18000, 'veg' => true],
            ['name' => 'Uttapam', 'price' => 16000, 'veg' => true],
            // --- Chinese & Thai ---
            ['name' => 'Veg Hakka Noodles', 'price' => 22000, 'veg' => true],
            ['name' => 'Gobi Manchurian Dry', 'price' => 23000, 'veg' => true],
            ['name' => 'Chilli Paneer', 'price' => 29000, 'veg' => true],
            ['name' => 'Veg Spring Rolls', 'price' => 18000, 'veg' => true],
            ['name' => 'Chicken Fried Rice', 'price' => 26000, 'veg' => false],
            ['name' => 'Manchow Soup', 'price' => 15000, 'veg' => true],
            ['name' => 'Sweet Corn Soup', 'price' => 14000, 'veg' => true],
            ['name' => 'Pad Thai Noodles', 'price' => 32000, 'veg' => false],
            ['name' => 'Thai Green Curry', 'price' => 38000, 'veg' => false],
            // ... (rest of the vast menu item list would be here) ...
            ['name' => 'Iced Tea', 'price' => 11000, 'veg' => true],
            ['name' => 'Mineral Water', 'price' => 2000, 'veg' => true],
        ];

        // Clear the table before seeding new data
        MenuItem::query()->delete();

        $totalAvailableItems = count($menuItemsData);

        // Process menus in chunks of 200 to keep memory usage low
        Menu::query()->chunk(200, function ($menus) use ($menuItemsData, $totalAvailableItems) {
            $itemsToInsert = [];
            foreach ($menus as $menu) {
                // Determine the number of items to create for this menu
                $desiredItemCount = rand(15, 25);
                
                // *** THE FIX IS HERE ***
                // Ensure we don't request more items than are available in the source array.
                $itemCount = min($desiredItemCount, $totalAvailableItems);

                // We must request at least 1 item.
                if ($itemCount < 1) {
                    continue; // Skip if there are no items to select from.
                }

                $randomKeys = array_rand($menuItemsData, $itemCount);

                if (!is_array($randomKeys)) {
                    $randomKeys = [$randomKeys];
                }

                foreach ($randomKeys as $key) {
                    $item = $menuItemsData[$key];
                    $itemsToInsert[] = [
                        'menu_id' => $menu->id,
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'veg' => $item['veg'],
                        'rating' => round(rand(38, 50) / 10, 1),
                        'image_url' => 'https://source.unsplash.com/400x300/?' . urlencode($item['name']),
                        'is_available' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            // Insert the items for the current chunk
            if (!empty($itemsToInsert)) {
                MenuItem::insert($itemsToInsert);
            }
        });
    }
}

