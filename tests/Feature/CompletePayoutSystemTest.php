<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, RestaurantOwner, Payout, Transaction};
use App\Jobs\ReconcilePayoutsJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class CompletePayoutSystemTest extends TestCase
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
    public function complete_payout_workflow_from_request_to_payment()
    {
        // Step 1: Restaurant owner creates payout request
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 100000, // ₹1000
                'notes' => 'Monthly payout request'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'pending');

        $payoutId = $response->json('data.id');

        // Step 2: Admin views all pending payouts
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v3/payouts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'pending');

        // Step 3: Admin approves the payout
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v3/payouts/{$payoutId}/approve", [
                'notes' => 'Approved for payment'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');

        // Step 4: Verify payout is approved in database
        $payout = Payout::find($payoutId);
        $this->assertEquals($this->admin->id, $payout->approved_by);

        // Step 5: Set external reference and simulate time passing
        $payout->external_reference = 'ref_' . uniqid();
        $payout->created_at = now()->subMinutes(35);
        $payout->save();
        
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Step 6: Verify payout is marked as paid and transaction is created
        $payout->refresh();
        $this->assertEquals('paid', $payout->status);
        $this->assertNotNull($payout->paid_at);

        $this->assertDatabaseHas('transactions', [
            'payout_id' => $payoutId,
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->restaurantOwner->id,
            'type' => 'payout',
            'amount' => 100000
        ]);
    }

    /** @test */
    public function payout_rejection_workflow()
    {
        // Step 1: Restaurant owner creates payout request
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 50000,
                'notes' => 'Payout request'
            ]);

        $payoutId = $response->json('data.id');

        // Step 2: Admin rejects the payout
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v3/payouts/{$payoutId}/reject", [
                'reason' => 'Insufficient documentation'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        // Step 3: Verify payout is rejected in database
        $this->assertDatabaseHas('payouts', [
            'id' => $payoutId,
            'status' => 'rejected',
            'rejected_by' => $this->admin->id
        ]);

        // Step 4: Verify no transaction is created for rejected payout
        $this->assertDatabaseMissing('transactions', [
            'payout_id' => $payoutId
        ]);
    }

    /** @test */
    public function multiple_payouts_processing()
    {
        // Create multiple payouts
        $payouts = [];
        for ($i = 0; $i < 5; $i++) {
            $token = $this->restaurantOwner->createToken('test')->plainTextToken;

            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/v2/owner/payouts', [
                    'restaurant_id' => $this->restaurant->id,
                    'amount' => 10000 + ($i * 10000),
                    'notes' => "Payout request {$i}"
                ]);

            $payouts[] = $response->json('data.id');
        }

        // Admin approves all payouts
        foreach ($payouts as $payoutId) {
            $response = $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v3/payouts/{$payoutId}/approve", [
                    'notes' => 'Approved'
                ]);

            $response->assertStatus(200);
            
            // Set external reference for reconciliation
            $payout = Payout::find($payoutId);
            $payout->external_reference = 'ref_' . uniqid();
            $payout->save();
        }

        // Simulate time passing and run reconcile job
        Payout::whereIn('id', $payouts)->update(['created_at' => now()->subMinutes(35)]);
        
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Verify all payouts are processed
        $paidPayouts = Payout::whereIn('id', $payouts)->where('status', 'paid')->count();
        $this->assertEquals(5, $paidPayouts);

        // Verify transactions are created
        $transactions = Transaction::whereIn('payout_id', $payouts)->count();
        $this->assertEquals(5, $transactions);
    }

    /** @test */
    public function payout_authorization_checks()
    {
        // Create another restaurant owner
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        $otherRestaurant = Restaurant::create([
            'name' => 'Other Restaurant',
            'slug' => 'other-restaurant',
            'city' => 'Other City',
            'is_active' => true
        ]);

        RestaurantOwner::create([
            'user_id' => $otherOwner->id,
            'restaurant_id' => $otherRestaurant->id,
            'is_active' => true
        ]);

        // Create payout by other owner
        $payout = Payout::create([
            'restaurant_id' => $otherRestaurant->id,
            'requested_by' => $otherOwner->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);

        // First owner cannot view other owner's payouts
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/owner/payouts');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data'); // Should not see other owner's payouts

        // First owner cannot create payout for other restaurant
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $otherRestaurant->id,
                'amount' => 50000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** @test */
    public function payout_validation_rules()
    {
        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        // Test minimum amount
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 500 // Less than minimum
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test maximum amount
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => $this->restaurant->id,
                'amount' => 10000000 // Exceeds maximum
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test invalid restaurant
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v2/owner/payouts', [
                'restaurant_id' => 999, // Non-existent
                'amount' => 50000
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** @test */
    public function payout_pagination_and_filtering()
    {
        // Create multiple payouts
        for ($i = 0; $i < 25; $i++) {
            Payout::create([
                'restaurant_id' => $this->restaurant->id,
                'requested_by' => $this->restaurantOwner->id,
                'amount' => 10000 + ($i * 1000),
                'status' => $i % 3 === 0 ? 'approved' : 'pending'
            ]);
        }

        $token = $this->restaurantOwner->createToken('test')->plainTextToken;

        // Test pagination
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/owner/payouts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function reconcile_job_handles_edge_cases()
    {
        // Create payouts with different statuses and ages
        $oldApproved = $this->createOldPayout([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 50000,
            'status' => 'approved',
            'external_reference' => 'ref_old_approved',
        ], 35);

        $recentApproved = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 30000,
            'status' => 'approved',
            'created_at' => now()->subMinutes(15)
        ]);

        $oldPending = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->restaurantOwner->id,
            'amount' => 40000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(35)
        ]);

        // Run reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Refresh payouts
        $oldApproved->refresh();
        $recentApproved->refresh();
        $oldPending->refresh();

        // Only old approved payout should be processed
        $this->assertEquals('paid', $oldApproved->status);
        $this->assertEquals('approved', $recentApproved->status);
        $this->assertEquals('pending', $oldPending->status);
    }

    private function createOldPayout(array $data, int $minutesAgo = 35): Payout
    {
        $payout = Payout::create($data);
        DB::table('payouts')->where('id', $payout->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);
        return $payout->fresh();
    }
}
