<?php

require_once 'vendor/autoload.php';

use App\Models\Payout;
use App\Models\User;
use App\Models\Restaurant;
use App\Jobs\ReconcilePayoutsJob;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create test data
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password'),
    'role' => 'restaurant'
]);

$restaurant = Restaurant::create([
    'name' => 'Test Restaurant',
    'slug' => 'test-restaurant',
    'city' => 'Test City',
    'is_active' => true
]);

// Create payout with external reference
$payout = Payout::create([
    'restaurant_id' => $restaurant->id,
    'requested_by' => $user->id,
    'amount' => 50000,
    'status' => 'approved',
    'external_reference' => 'ref_test_123'
]);

// Set created_at to 35 minutes ago
DB::table('payouts')->where('id', $payout->id)->update(['created_at' => now()->subMinutes(35)]);

echo "Created payout: {$payout->id}\n";
echo "External reference: {$payout->external_reference}\n";
echo "Status: {$payout->status}\n";
echo "Created at: {$payout->created_at}\n";

// Check what the job query finds
$payouts = Payout::whereIn('status', ['approved', 'processing'])
    ->where('created_at', '<', now()->subMinutes(30))
    ->whereNotNull('external_reference')
    ->limit(200)
    ->get();

echo "Job query found {$payouts->count()} payouts\n";

foreach ($payouts as $p) {
    echo "Payout {$p->id}: status={$p->status}, external_ref={$p->external_reference}, created_at={$p->created_at}\n";
}

// Run the job
$job = new ReconcilePayoutsJob();
$job->handle();

// Check result
$payout->refresh();
echo "After job: status={$payout->status}\n";
