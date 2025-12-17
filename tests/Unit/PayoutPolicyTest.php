<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{User, Restaurant, Payout};
use App\Policies\PayoutPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayoutPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $restaurantOwner;
    protected $regularUser;
    protected $restaurant;
    protected $payout;

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

        // Create regular user
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        // Create payout
        $this->payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function admin_can_view_any_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertTrue($policy->view($this->admin, $this->payout));
    }

    /** @test */
    public function restaurant_owner_can_view_their_own_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertTrue($policy->view($this->restaurantOwner, $this->payout));
    }

    /** @test */
    public function restaurant_owner_cannot_view_other_owners_payout()
    {
        // Create another restaurant owner
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $policy = new PayoutPolicy();
        
        $this->assertFalse($policy->view($otherOwner, $this->payout));
    }

    /** @test */
    public function regular_user_cannot_view_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertFalse($policy->view($this->regularUser, $this->payout));
    }

    /** @test */
    public function admin_can_create_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertTrue($policy->create($this->admin));
    }

    /** @test */
    public function restaurant_owner_can_create_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertTrue($policy->create($this->restaurantOwner));
    }

    /** @test */
    public function regular_user_cannot_create_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertFalse($policy->create($this->regularUser));
    }

    /** @test */
    public function policy_handles_null_user()
    {
        $policy = new PayoutPolicy();
        
        $this->assertFalse($policy->view(null, $this->payout));
        $this->assertFalse($policy->create(null));
    }

    /** @test */
    public function policy_handles_null_payout()
    {
        $policy = new PayoutPolicy();
        
        $this->assertFalse($policy->view($this->admin, null));
    }

    /** @test */
    public function policy_works_with_different_user_roles()
    {
        $policy = new PayoutPolicy();
        
        // Test with different user roles
        $deliveryUser = User::create([
            'name' => 'Delivery User',
            'email' => 'delivery@example.com',
            'password' => bcrypt('password'),
            'role' => 'delivery'
        ]);

        $this->assertFalse($policy->view($deliveryUser, $this->payout));
        $this->assertFalse($policy->create($deliveryUser));
    }

    /** @test */
    public function policy_works_with_different_payout_statuses()
    {
        $policy = new PayoutPolicy();
        
        // Test with different payout statuses
        $approvedPayout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 30000,
            'status' => 'approved'
        ]);

        $rejectedPayout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 40000,
            'status' => 'rejected'
        ]);

        // Restaurant owner should be able to view their payouts regardless of status
        $this->assertTrue($policy->view($this->restaurantOwner, $approvedPayout));
        $this->assertTrue($policy->view($this->restaurantOwner, $rejectedPayout));
        
        // Admin should be able to view all payouts
        $this->assertTrue($policy->view($this->admin, $approvedPayout));
        $this->assertTrue($policy->view($this->admin, $rejectedPayout));
    }
}