<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Call seeders in the correct order
        $this->call([
            RestaurantSeeder::class,
            MenuSeeder::class,
            MenuItemSeeder::class,
            AdminUserSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}