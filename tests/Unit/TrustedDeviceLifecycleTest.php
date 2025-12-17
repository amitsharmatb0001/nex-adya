<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrustedDeviceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected TrustedDeviceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrustedDeviceService();
    }

    public function test_revoke_and_rotates_on_touch()
    {
        // Create user
        $user = User::factory()->create();

        // Create initial trusted device
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser(
            $user, 
            $request, 
            'Test Device', 
            365
        );

        $device = TrustedDevice::find($devId);
        $originalTokenHash = $device->token_hash;
        $originalLastUsed = $device->last_used_at;

        // Simulate time passing
        $this->travel(1)->hour();

        // Create new request to trigger token rotation
        $newRequest = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Call touch with rotation enabled
        [$device, $newRawToken] = $this->service->touch($device, $newRequest, true);

        // Verify device is still valid
        $this->assertNotNull($device);
        $this->assertEquals($devId, $device->id);

        // Verify last_used_at was updated
        $this->assertNotEquals($originalLastUsed, $device->last_used_at);
        if ($originalLastUsed) {
            $this->assertTrue($device->last_used_at->isAfter($originalLastUsed));
        } else {
            $this->assertNotNull($device->last_used_at);
        }

        // Verify token was rotated (new hash)
        $this->assertNotEquals($originalTokenHash, $device->token_hash);

        // Test that old token no longer works
        $oldPayload = base64_encode($devId . '|' . $rawToken);
        $oldTokenDevice = $this->service->validateCookie($oldPayload, $user);
        $this->assertNull($oldTokenDevice);
    }

    public function test_device_revocation()
    {
        $user = User::factory()->create();
        $request = Request::create('/test');

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser($user, $request, 'Test Device', 365);

        $device = TrustedDevice::find($devId);
        $this->assertTrue($device->is_active);

        // Revoke device
        $device->update(['is_active' => false]);

        // Test that revoked device is rejected
        $payload = base64_encode($devId . '|' . $rawToken);
        $validatedDevice = $this->service->validateCookie($payload, $user);
        $this->assertNull($validatedDevice);
    }

    public function test_device_expiry()
    {
        $user = User::factory()->create();
        $request = Request::create('/test');

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser($user, $request, 'Test Device', 1);

        $device = TrustedDevice::find($devId);
        $this->assertTrue($device->is_active);

        // Travel to after expiry
        $this->travel(2)->days();

        // Test that expired device is rejected
        $payload = base64_encode($devId . '|' . $rawToken);
        $validatedDevice = $this->service->validateCookie($payload, $user);
        $this->assertNull($validatedDevice);
    }

    public function test_multiple_devices_per_user()
    {
        $user = User::factory()->create();

        // Create multiple devices
        $request1 = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Browser 1)',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        $request2 = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Browser 2)',
            'REMOTE_ADDR' => '192.168.1.2'
        ]);

        [$devId1, $rawToken1, $expiresAt1] = $this->service->createForUser($user, $request1, 'Device 1', 365);
        [$devId2, $rawToken2, $expiresAt2] = $this->service->createForUser($user, $request2, 'Device 2', 365);

        // Verify both devices exist
        $this->assertCount(2, TrustedDevice::where('user_id', $user->id)->get());

        // Test both devices work
        $payload1 = base64_encode($devId1 . '|' . $rawToken1);
        $payload2 = base64_encode($devId2 . '|' . $rawToken2);

        $device1 = $this->service->validateCookie($payload1, $user);
        $device2 = $this->service->validateCookie($payload2, $user);

        $this->assertNotNull($device1);
        $this->assertNotNull($device2);
        $this->assertEquals($devId1, $device1->id);
        $this->assertEquals($devId2, $device2->id);
    }

    public function test_device_cleanup_on_user_deletion()
    {
        $user = User::factory()->create();
        $request = Request::create('/test');

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser($user, $request, 'Test Device', 365);

        // Verify device exists
        $this->assertNotNull(TrustedDevice::find($devId));

        // Delete user
        $user->delete();

        // Verify device is also deleted (cascade)
        $this->assertNull(TrustedDevice::find($devId));
    }
}
