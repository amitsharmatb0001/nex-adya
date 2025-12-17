<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\{User, Restaurant, Menu, MenuItem, Address};
use App\Jobs\SendOrderPlacedNotifications;

class OrderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_dispatched_on_order_create(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 12345]);
        $addr = Address::create([
            'user_id' => $user->id, 'line1' => '123', 'city' => 'Delhi', 'pincode' => '110001'
        ]);

        $payload = [
            'restaurant_id' => $restaurant->id,
            'address_id'    => $addr->id,
            'items'         => [['menu_item_id' => $item->id, 'qty' => 1]],
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('ok', true);

        Queue::assertPushed(SendOrderPlacedNotifications::class, 1);
    }
}
