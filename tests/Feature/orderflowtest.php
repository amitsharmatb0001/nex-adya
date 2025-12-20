<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Restaurant, Menu, MenuItem, Address};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ✅ P3 FIX #15: Sample Feature Tests
 * 
 * SAVE AS: tests/Feature/OrderFlowTest.php
 * 
 * These tests demonstrate how to test critical user flows.
 * Run with: php artisan test --filter=OrderFlowTest
 */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: User can browse restaurants and create an order
     */
    public function test_user_can_create_order_successfully()
    {
        // Arrange: Set up test data
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'city' => 'Mumbai',
            'rating' => 4.5
        ]);
        
        $menu = Menu::factory()->create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Main Menu'
        ]);
        
        $item = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Butter Chicken',
            'price' => 35000, // ₹350
            'is_available' => true
        ]);
        
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'line1' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001'
        ]);

        // Act: Create order
        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'restaurant_id' => $restaurant->id,
            'address_id' => $address->id,
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'qty' => 2
                ]
            ],
            'notes' => 'Extra spicy please'
        ]);

        // Assert: Check response
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'ok',
                     'data' => [
                         'order_id',
                         'status',
                         'total'
                     ]
                 ]);

        // Assert: Check database
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'total' => 70000, // 2 items × ₹350
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'price' => 35000
        ]);
    }

    /**
     * Test: User cannot order from inactive restaurant
     */
    public function test_user_cannot_order_from_inactive_restaurant()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'is_active' => false // Inactive restaurant
        ]);
        
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'restaurant_id' => $restaurant->id,
            'address_id' => $address->id,
            'items' => [['menu_item_id' => $item->id, 'qty' => 1]]
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id
        ]);
    }

    /**
     * Test: User cannot order unavailable items
     */
    public function test_user_cannot_order_unavailable_items()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        
        $item = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'is_available' => false // Item not available
        ]);
        
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'restaurant_id' => $restaurant->id,
            'address_id' => $address->id,
            'items' => [['menu_item_id' => $item->id, 'qty' => 1]]
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error.message', 'Some items are unavailable.');
    }

    /**
     * Test: User can view their order history
     */
    public function test_user_can_view_order_history()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        
        // Create 3 orders for user
        $orders = [];
        for ($i = 0; $i < 3; $i++) {
            $orders[] = \App\Models\Order::factory()->create([
                'user_id' => $user->id,
                'restaurant_id' => $restaurant->id,
                'status' => 'delivered'
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/orders?scope=delivered');

        $response->assertOk()
                 ->assertJsonCount(3, 'data.items');
    }

    /**
     * Test: User cannot see other users' orders
     */
    public function test_user_cannot_see_other_users_orders()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $order = \App\Models\Order::factory()->create([
            'user_id' => $user2->id
        ]);

        // User1 tries to view User2's order
        $response = $this->actingAs($user1)->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(404); // Order not found (because it's not theirs)
    }

    /**
     * Test: Order total is calculated correctly
     */
    public function test_order_total_calculated_correctly()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        
        $item1 = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'price' => 25000, // ₹250
            'is_available' => true
        ]);
        
        $item2 = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'price' => 15000, // ₹150
            'is_available' => true
        ]);
        
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'restaurant_id' => $restaurant->id,
            'address_id' => $address->id,
            'items' => [
                ['menu_item_id' => $item1->id, 'qty' => 2], // 2 × ₹250 = ₹500
                ['menu_item_id' => $item2->id, 'qty' => 3]  // 3 × ₹150 = ₹450
            ]
        ]);

        $response->assertStatus(201);
        
        // Total should be ₹500 + ₹450 = ₹950
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 95000 // ₹950 in paise
        ]);
    }
}