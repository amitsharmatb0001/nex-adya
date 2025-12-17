<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, Menu, MenuItem, Address, Order, OrderItem};

class OrderUpdateItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_items_on_pending_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $restaurant = Restaurant::factory()->create(['is_active'=>true]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $i1 = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 1500, 'is_available' => true]);
        $i2 = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 1000, 'is_available' => true]);

        $addr = \App\Models\Address::factory()->create(['user_id' => $user->id, 'city'=>'Delhi', 'line1'=>'123', 'pincode'=>'110001']);

        // Create a base order
        $order = Order::factory()->create([
            'user_id' => $user->id, 'restaurant_id' => $restaurant->id,
            'address_id' => $addr->id, 'status' => 'pending', 'total' => 1500
        ]);
        OrderItem::create(['order_id'=>$order->id, 'menu_item_id'=>$i1->id, 'quantity'=>1, 'price'=>1500]);

        // Update: remove i1 (qty 0), add i2 x2
        $payload = [
            'items' => [
                ['menu_item_id' => $i1->id, 'qty' => 0],
                ['menu_item_id' => $i2->id, 'qty' => 2],
            ],
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/orders/{$order->id}", $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 2000);
    }

    public function test_cannot_update_non_pending_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['menu_id' => $menu->id, 'price' => 1000, 'is_available' => true]);
        $addr = \App\Models\Address::factory()->create(['user_id' => $user->id, 'city'=>'Delhi', 'line1'=>'123', 'pincode'=>'110001']);

        $order = Order::factory()->create([
            'user_id'=>$user->id,'restaurant_id'=>$restaurant->id,'address_id'=>$addr->id,
            'status'=>'paid','total'=>1000
        ]);
        OrderItem::create(['order_id'=>$order->id,'menu_item_id'=>$item->id,'quantity'=>1,'price'=>1000]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/orders/{$order->id}", [
                'items' => [['menu_item_id'=>$item->id, 'qty'=>2]]
            ])
            ->assertStatus(422);
    }
}
