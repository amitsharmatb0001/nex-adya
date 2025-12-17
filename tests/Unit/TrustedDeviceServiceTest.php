<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class TrustedDeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TrustedDeviceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrustedDeviceService();
    }

    public function test_create_and_validate_token()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a mock request
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Create trusted device
        [$devId, $rawToken, $expiresAt] = $this->service->createForUser(
            $user, 
            $request, 
            'Test Device', 
            365
        );

        // Verify device was created in database
        $device = TrustedDevice::find($devId);
        $this->assertNotNull($device);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertEquals('Test Device', $device->device_name);
        $this->assertEquals('192.168.1.1', $device->ip_address);
        $this->assertEquals('Mozilla/5.0 (Test Browser)', $device->user_agent);
        $this->assertTrue($device->is_active);

        // Verify token hash is stored (not raw token)
        $this->assertNotEquals($rawToken, $device->token_hash);
        // Verify HMAC hash matches
        $secretPart = explode('.', $rawToken)[1];
        $expectedHash = hash_hmac('sha256', $secretPart, config('app.key'));
        $this->assertEquals($expectedHash, $device->token_hash);

        // Test cookie validation
        $payload = base64_encode($devId . '|' . $rawToken);
        $validatedDevice = $this->service->validateCookie($payload, $user);

        $this->assertNotNull($validatedDevice);
        $this->assertEquals($devId, $validatedDevice->id);
        $this->assertEquals($user->id, $validatedDevice->user_id);

        // Test with invalid token
        $invalidPayload = base64_encode($devId . '|' . 'invalid_token');
        $invalidDevice = $this->service->validateCookie($invalidPayload, $user);
        $this->assertNull($invalidDevice);

        // Test with invalid device ID
        $invalidPayload2 = base64_encode('999999' . '|' . $rawToken);
        $invalidDevice2 = $this->service->validateCookie($invalidPayload2, $user);
        $this->assertNull($invalidDevice2);
    }

    public function test_create_for_user_with_minimal_data()
    {
        $user = User::factory()->create();
        $request = Request::create('/test');

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser($user, $request);

        $device = TrustedDevice::find($devId);
        $this->assertNotNull($device);
        $this->assertEquals($user->id, $device->user_id);
        // Service sets default values instead of null
        $this->assertNotNull($device->device_name);
        $this->assertNotNull($device->ip_address);
        $this->assertNotNull($device->user_agent);
    }

    public function test_expires_at_is_correct()
    {
        $user = User::factory()->create();
        $request = Request::create('/test');

        [$devId, $rawToken, $expiresAt] = $this->service->createForUser($user, $request, null, 30);

        $device = TrustedDevice::find($devId);
        $this->assertNotNull($device);
        
        // Check that expires_at is approximately 30 days from now
        $expectedExpiry = now()->addDays(30);
        $this->assertTrue($device->expires_at->diffInMinutes($expectedExpiry) < 5);
    }
}
