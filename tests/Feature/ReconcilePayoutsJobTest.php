<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, Payout, Transaction};
use App\Jobs\ReconcilePayoutsJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReconcilePayoutsJobTest extends TestCase
{
    use RefreshDatabase;

    protected $restaurant;
    protected $owner;
    protected $admin;

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
        $this->owner = User::create([
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
    }

    /**
     * Helper method to create a payout with old created_at timestamp
     */
    private function createOldPayout(array $data, int $minutesAgo = 35): Payout
    {
        $payout = Payout::create($data);
        DB::table('payouts')->where('id', $payout->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);
        return $payout->fresh();
    }

    /** @test */
    public function reconcile_job_processes_approved_payouts_older_than_30_minutes()
    {
        // Create an approved payout older than 30 minutes
        $oldPayout = $this->createOldPayout([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'external_reference' => 'ref_old_123'
        ]);

        // Create a recent approved payout (should not be processed)
        $recentPayout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 30000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(15),
            'approved_by' => $this->admin->id
        ]);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Refresh the payout from database
        $oldPayout->refresh();
        $recentPayout->refresh();

        // The old payout should be marked as paid
        $this->assertEquals('paid', $oldPayout->status);
        $this->assertNotNull($oldPayout->paid_at);

        // The recent payout should remain approved
        $this->assertEquals('approved', $recentPayout->status);
        $this->assertNull($recentPayout->paid_at);
    }

    /** @test */
    public function reconcile_job_creates_transaction_for_paid_payouts()
    {
        // Create an approved payout older than 30 minutes
        $payout = $this->createOldPayout([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'external_reference' => 'ref_test_123'
        ], 35);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Check that a transaction was created
        $this->assertDatabaseHas('transactions', [
            'payout_id' => $payout->id,
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->owner->id,
            'type' => 'payout',
            'amount' => 50000,
            'currency' => 'INR'
        ]);
    }

    /** @test */
    public function reconcile_job_handles_processing_payouts()
    {
        // Create a processing payout older than 30 minutes
        $processingPayout = $this->createOldPayout([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 40000,
            'status' => 'processing',
            'external_reference' => 'ref_processing_456'
        ], 35);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // The processing payout should be marked as paid
        $processingPayout->refresh();
        $this->assertEquals('paid', $processingPayout->status);
        $this->assertNotNull($processingPayout->paid_at);
    }

    /** @test */
    public function reconcile_job_does_not_process_pending_payouts()
    {
        // Create a pending payout older than 30 minutes
        $pendingPayout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(35)
        ]);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // The pending payout should remain pending
        $pendingPayout->refresh();
        $this->assertEquals('pending', $pendingPayout->status);
        $this->assertNull($pendingPayout->paid_at);
    }

    /** @test */
    public function reconcile_job_handles_failed_payouts()
    {
        // Create an approved payout older than 30 minutes
        $payout = $this->createOldPayout([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'external_reference' => 'ref_failed_789F'
        ], 35);

        // Mock the fakeProviderLookup to return 'failed'
        $job = new class extends ReconcilePayoutsJob {
            public function fakeProviderLookup($payout): ?string
            {
                return 'failed';
            }
        };

        $job->handle();

        // The payout should be marked as failed
        $payout->refresh();
        $this->assertEquals('failed', $payout->status);
    }

    /** @test */
    public function reconcile_job_processes_payouts_in_chunks()
    {
        // Create multiple payouts to test chunking
        for ($i = 0; $i < 150; $i++) {
            $payout = Payout::create([
                'restaurant_id' => $this->restaurant->id,
                'requested_by' => $this->owner->id,
                'amount' => 10000 + $i,
                'status' => 'approved',
                'approved_at' => now()->subMinutes(35),
                'approved_by' => $this->admin->id,
                'external_reference' => 'ref_chunk_' . $i
            ]);
            
            // Manually set created_at to 35 minutes ago
            DB::table('payouts')->where('id', $payout->id)->update(['created_at' => now()->subMinutes(35)]);
        }


        // Run the reconcile job multiple times to process all chunks
        $job = new ReconcilePayoutsJob();
        
        // First run should process all 150 payouts (limit is 200)
        $job->handle();
        $paidPayouts = Payout::where('status', 'paid')->count();
        $this->assertEquals(150, $paidPayouts);
        
        // Second run should process no more payouts (all already processed)
        $job->handle();
        $paidPayouts = Payout::where('status', 'paid')->count();
        $this->assertEquals(150, $paidPayouts);
    }

    /** @test */
    public function reconcile_job_handles_exceptions_gracefully()
    {
        // Create a payout that will cause an exception
        $payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'created_at' => now()->subMinutes(35)
        ]);

        // Mock the fakeProviderLookup to throw an exception
        $job = new class extends ReconcilePayoutsJob {
            public function fakeProviderLookup($payout): ?string
            {
                throw new \Exception('Provider API error');
            }
        };

        // The job should not fail completely
        $job->handle();

        // The payout should remain in its original state
        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
    }

    /** @test */
    public function reconcile_job_does_not_create_duplicate_transactions()
    {
        // Create an approved payout older than 30 minutes
        $payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'created_at' => now()->subMinutes(35)
        ]);

        // Create an existing transaction for this payout
        Transaction::create([
            'payout_id' => $payout->id,
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->owner->id,
            'type' => 'payout',
            'amount' => 50000,
            'currency' => 'INR',
            'reference' => 'existing-ref'
        ]);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();

        // Should not create duplicate transactions
        $transactionCount = Transaction::where('payout_id', $payout->id)->count();
        $this->assertEquals(1, $transactionCount);
    }

    /** @test */
    public function reconcile_job_sets_correct_metadata_in_transaction()
    {
        // Create an approved payout with payment provider info
        $payout = Payout::create([
            'restaurant_id' => $this->restaurant->id,
            'requested_by' => $this->owner->id,
            'amount' => 50000,
            'status' => 'approved',
            'approved_at' => now()->subMinutes(35),
            'approved_by' => $this->admin->id,
            'payment_provider' => 'razorpay',
            'external_reference' => 'ref_123456'
        ]);
        
        // Manually set created_at to 35 minutes ago
        DB::table('payouts')->where('id', $payout->id)->update(['created_at' => now()->subMinutes(35)]);

        // Run the reconcile job
        $job = new ReconcilePayoutsJob();
        $job->handle();


        // Check transaction metadata
        $transaction = Transaction::where('payout_id', $payout->id)->first();
        $this->assertNotNull($transaction);
        // The reference should be the settled_at timestamp from provider response
        $this->assertNotNull($transaction->reference);
        $this->assertIsString($transaction->reference);
    }
}
