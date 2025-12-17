<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, Menu, MenuItem, Address};

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_order(): void
    {
        // Data setup
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $item1 = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 19900]);
        $item2 = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 9900]);
        $address = Address::factory()->create(['user_id' => $user->id]);

        // Token (login flow elsewhere; here we act as authenticated)
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'restaurant_id' => $restaurant->id,
            'address_id'    => $address->id,
            'items'         => [
                ['menu_item_id' => $item1->id, 'qty' => 1],
                ['menu_item_id' => $item2->id, 'qty' => 2],
            ],
        ];

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('ok', true);

        // Total should be computed from DB prices: 19900 + (9900*2) = 39700 paise
        $this->assertEquals(39700, $res->json('data.total'));
    }
}
