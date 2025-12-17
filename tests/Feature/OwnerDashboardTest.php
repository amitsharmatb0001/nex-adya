<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantOwner;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_dashboard()
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['role' => 'restaurant']);
        RestaurantOwner::factory()->create(['user_id' => $user->id, 'restaurant_id' => $restaurant->id, 'is_active' => true]);

        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/dashboard');

        $res->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok','data' => ['restaurant_id','overview']]);
    }
}
