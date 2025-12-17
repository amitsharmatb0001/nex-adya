<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = 'admin@example.com';

        if (!User::where('email', $adminEmail)->exists()) {
            User::create([
                'name'     => 'Super Admin',
                'email'    => $adminEmail,
                'password' => Hash::make('Admin@12345!'), // strong default
                'role'     => 'admin',
            ]);
        }
    }
}
