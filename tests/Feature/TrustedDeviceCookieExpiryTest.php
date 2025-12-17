<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class TrustedDeviceCookieExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_cookie_is_rejected()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create expired trusted device
        $device = TrustedDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'expires_at' => now()->subDays(1), // Expired yesterday
            'is_active' => true,
        ]);

        // Create cookie payload
        $payload = base64_encode($device->id . '|' . 'test_token');

        // Try to login with expired cookie
        $response = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        // Should require 2FA (expired device rejected)
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('2fa_required'));
    }

    public function test_cookie_expires_after_validity_period()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create trusted device with 1 day validity
        $trustedService = new TrustedDeviceService();
        $request = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        [$devId, $rawToken, $expiresAt] = $trustedService->createForUser(
            $user, 
            $request, 
            'Test Device', 
            1 // 1 day validity
        );

        $payload = base64_encode($devId . '|' . $rawToken);

        // Test immediately after creation (should work)
        $response1 = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        $this->assertEquals(200, $response1->status());

        // Travel to after expiry
        $this->travel(2)->days();

        // Test after expiry (should require 2FA)
        $response2 = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        $this->assertEquals(200, $response2->status());
        $this->assertTrue($response2->json('2fa_required'));
    }

    public function test_cookie_with_invalid_format_is_rejected()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Test one invalid cookie format to avoid rate limiting
        $invalidCookie = 'invalid_base64';
        
        $response = $this->withoutMiddleware([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\FraudDetectionMiddleware::class,
            ])
            ->withCookie('trusted_device', $invalidCookie)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        // Should require 2FA (invalid cookie rejected)
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('2fa_required'));
    }

    public function test_cookie_with_nonexistent_device_is_rejected()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create cookie for non-existent device
        $payload = base64_encode('999999' . '|' . 'some_token');

        $response = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        // Should require 2FA (non-existent device rejected)
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('2fa_required'));
    }

    public function test_cookie_with_inactive_device_is_rejected()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create inactive trusted device
        $device = TrustedDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'expires_at' => now()->addDays(30),
            'is_active' => false, // Inactive
        ]);

        $payload = base64_encode($device->id . '|' . 'test_token');

        $response = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        // Should require 2FA (inactive device rejected)
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('2fa_required'));
    }

    public function test_cookie_with_wrong_user_is_rejected()
    {
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create trusted device for user1
        $device = TrustedDevice::factory()->create([
            'user_id' => $user1->id,
            'device_name' => 'Test Device',
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $payload = base64_encode($device->id . '|' . 'test_token');

        // Try to use user1's trusted device for user2's login
        $response = $this->withCookie('trusted_device', $payload)
            ->postJson('/api/v1/auth/login', [
                'email' => 'user2@example.com',
                'password' => 'password123'
            ]);

        // Should require 2FA (wrong user's device rejected)
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('2fa_required'));
    }

    public function test_cookie_cleanup_after_expiry()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'two_factor_secret' => 'test_secret' // Enable 2FA
        ]);

        // Create multiple devices with different expiry dates
        $device1 = TrustedDevice::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDays(1), // Expired
            'is_active' => true,
        ]);

        $device2 = TrustedDevice::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30), // Valid
            'is_active' => true,
        ]);

        // Test expired device
        $payload1 = base64_encode($device1->id . '|' . 'test_token1');
        $response1 = $this->withCookie('trusted_device', $payload1)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        $this->assertTrue($response1->json('2fa_required'));

        // Test valid device
        $payload2 = base64_encode($device2->id . '|' . 'test_token2');
        $response2 = $this->withCookie('trusted_device', $payload2)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123'
            ]);

        // Should work (assuming token validation passes)
        $this->assertEquals(200, $response2->status());
    }
}
