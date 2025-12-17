<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Restaurant;
use Illuminate\Support\Str;

class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $places = [];

        // Define exactly 11 states.
        $states = [
            'Rajasthan', 'Maharashtra', 'Karnataka', 'Tamil Nadu', 'Uttar Pradesh',
            'West Bengal', 'Gujarat', 'Delhi', 'Punjab', 'Kerala', 'Madhya Pradesh'
        ];

        // --- Data for generating varied and realistic entries ---
        $restaurantPrefixes = ['Royal', 'Grand', 'Spice', 'Urban', 'Saffron', 'Coastal', 'Golden', 'Heritage', 'City', 'Majestic', 'The'];
        $restaurantSuffixes = ['Palace', 'Kitchen', 'Grill', 'Dhaba', 'Bites', 'Tandoor', 'Diner', 'Junction', 'Feast', 'Courtyard', 'Affair'];
        $restaurantCuisines = ['Indian', 'Chinese', 'Italian', 'Mexican', 'Continental', 'Thai', 'Mughlai'];
        $restaurantImages = ['/images/restaurants/res1.jpg', '/images/restaurants/res2.jpg', '/images/restaurants/res3.jpg', '/images/restaurants/res4.jpg'];
        
        $cafePrefixes = ['The Daily', 'Cozy', 'Aroma', 'Sunrise', 'Bean', 'Book Nook', 'Mocha', 'Cyber', 'Central', 'The Glass', 'Old Town'];
        $cafeSuffixes = ['Grind', 'Corner', 'Brews', 'Scene', 'Cafe', 'Hub', 'House', 'Spot', 'Lounge', 'Bakery', 'Roasters'];
        $cafeCuisines = ['Cafe', 'Bakery', 'Fast Food', 'Desserts', 'Beverages'];
        $cafeImages = ['/images/cafes/cafe1.jpg', '/images/cafes/cafe2.jpg', '/images/cafes/cafe3.jpg'];

        foreach ($states as $stateName) {
            // Generate exactly 25 cities for each state
            for ($c = 1; $c <= 25; $c++) {
                $cityName = "City {$c} - {$stateName}";

                // Generate exactly 25 restaurants for the current city
                for ($i = 1; $i <= 25; $i++) {
                    $name = $restaurantPrefixes[array_rand($restaurantPrefixes)] . " " . $restaurantSuffixes[array_rand($restaurantSuffixes)] . " #{$i}";
                    $places[] = [
                        'name' => $name,
                        'slug' => Str::slug($name . ' ' . $cityName),
                        'image' => $restaurantImages[array_rand($restaurantImages)],
                        'rating' => round(rand(35, 50) / 10, 1), // Generates a rating between 3.5 and 5.0
                        'eta_minutes' => rand(20, 55),
                        'cuisine' => $restaurantCuisines[array_rand($restaurantCuisines)],
                        'city' => $cityName,
                    ];
                }

                // Generate exactly 11 cafes for the current city
                for ($j = 1; $j <= 11; $j++) {
                    $name = $cafePrefixes[array_rand($cafePrefixes)] . " " . $cafeSuffixes[array_rand($cafeSuffixes)] . " #{$j}";
                    $places[] = [
                        'name' => $name,
                        'slug' => Str::slug($name . ' ' . $cityName),
                        'image' => $cafeImages[array_rand($cafeImages)],
                        'rating' => round(rand(38, 50) / 10, 1), // Generates a rating between 3.8 and 5.0
                        'eta_minutes' => rand(15, 40),
                        'cuisine' => $cafeCuisines[array_rand($cafeCuisines)],
                        'city' => $cityName,
                    ];
                }
            }
        }

        // Chunk the inserts to avoid memory issues with large datasets
        foreach (array_chunk($places, 500) as $chunk) {
            Restaurant::insert($chunk);
        }
    }
}

