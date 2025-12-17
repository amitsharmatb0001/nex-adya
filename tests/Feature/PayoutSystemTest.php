<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, RestaurantOwner, Payout};

class PayoutSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $restaurantOwner;
    protected $admin;
    protected $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        // Create restaurant owner
        $this->restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        // Create restaurant owner relationship
        RestaurantOwner::create([
            'user_id' => $this->restaurantOwner->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function restaurant_owner_can_view_their_payouts()
    {
        // Create some payouts for the owner
        Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000, // ₹500
            'status' => 'pending'
        ]);

        Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 75000, // ₹750
            'status' => 'approved'
        ]);

        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/owner/payouts');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function restaurant_owner_can_create_payout_request()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 100000 // ₹1000
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payouts', [
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 100000,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function restaurant_owner_cannot_create_payout_with_invalid_amount()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 500 // Less than minimum ₹10
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function restaurant_owner_cannot_create_payout_for_other_restaurant()
    {
        // Create another restaurant
        $otherRestaurant = Restaurant::create([
            'name' => 'Other Restaurant',
            'slug' => 'other-restaurant',
            'city' => 'Other City',
            'is_active' => true
        ]);

        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $otherRestaurant->id,
                'amount' => 100000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** @test */
    public function admin_can_view_all_payouts()
    {
        // Create payouts for different owners
        Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/payouts');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data');
    }

    /** @test */
    public function admin_can_approve_payout()
    {
        $payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v2/payouts/{$payout->id}/approve", [
                'notes' => 'Approved for payment'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id
        ]);
    }

    /** @test */
    public function admin_can_reject_payout()
    {
        $payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v2/payouts/{$payout->id}/reject", [
                'notes' => 'Insufficient documentation'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'rejected',
            'approved_by' => $this->admin->id
        ]);
    }

    /** @test */
    public function admin_cannot_approve_nonexistent_payout()
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/payouts/999/approve', [
                'notes' => 'Test approval'
            ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_payout_endpoints()
    {
        $response = $this->getJson('/api/v2/owner/payouts');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v2/payouts');
        $response->assertStatus(401);
    }

    /** @test */
    public function regular_user_cannot_access_restaurant_owner_endpoints()
    {
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        $token = $regularUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/owner/payouts');

        $response->assertStatus(403);
    }

    /** @test */
    public function regular_user_cannot_access_admin_endpoints()
    {
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        $token = $regularUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/payouts');

        $response->assertStatus(403);
    }

    /** @test */
    public function restaurant_owner_cannot_access_admin_endpoints()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/payouts');

        $response->assertStatus(403);
    }

    /** @test */
    public function payout_creation_requires_valid_restaurant()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => 999, // Non-existent restaurant
                'amount' => 100000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** @test */
    public function payout_amount_must_be_positive_integer()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => -1000 // Negative amount
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
