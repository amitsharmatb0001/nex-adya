<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{Payout, User, Restaurant};
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function payout_belongs_to_restaurant()
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        $owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $payout = Payout::create([
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $this->assertInstanceOf(Restaurant::class, $payout->restaurant);
        $this->assertEquals($restaurant->id, $payout->restaurant->id);
    }

    /** @test */
    public function payout_belongs_to_owner()
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        $owner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $payout = Payout::create([
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $this->assertInstanceOf(User::class, $payout->requester);
        $this->assertEquals($owner->id, $payout->requester->id);
    }

    /** @test */
    public function payout_belongs_to_approved_by_user()
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        $owner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $payout = Payout::create([
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_by' => $admin->id
        ]);

        $this->assertInstanceOf(User::class, $payout->approver);
        $this->assertEquals($admin->id, $payout->approver->id);
    }

    /** @test */
    public function payout_can_be_created_with_required_fields()
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        $owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $payout = Payout::create([
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('payouts', [
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function payout_can_be_created_with_optional_fields()
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        $owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $payout = Payout::create([
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'notes' => 'Test notes',
            'approved_at' => now(),
            'approved_by' => $admin->id
        ]);

        $this->assertDatabaseHas('payouts', [
            'restaurant_id' => $restaurant->id,
            'requested_by' => $owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'notes' => 'Test notes',
            'approved_by' => $admin->id
        ]);
    }

    /** @test */
    public function payout_fillable_fields_are_correct()
    {
        $payout = new Payout();
        $fillable = $payout->getFillable();

        $expectedFillable = [
            'restaurant_id',
            'requested_by',
            'external_reference',
            'amount',
            'currency',
            'status',
            'notes',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'paid_at',
            'payment_provider',
            'provider_response',
            'is_active'
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }
}
