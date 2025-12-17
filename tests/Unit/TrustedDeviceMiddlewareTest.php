<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use App\Http\Middleware\CheckTrustedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class TrustedDeviceMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test route that uses the middleware
        Route::middleware(CheckTrustedDevice::class)->get('/test-route', function () {
            return response()->json(['message' => 'success']);
        });
    }

    public function test_trusted_cookie_bypass_2fa()
    {
        // Create user
        $user = User::factory()->create();

        // Create trusted device
        $trustedService = new TrustedDeviceService();
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        [$devId, $rawToken, $expiresAt] = $trustedService->createForUser(
            $user, 
            $request, 
            'Test Device', 
            365
        );

        // Create cookie payload
        $payload = base64_encode($devId . '|' . $rawToken);

        // Create request with trusted device cookie
        $request = Request::create('/test-route', 'GET');
        $request->cookies->set('trusted_device', $payload);

        // Mock authentication
        $this->actingAs($user);

        // Test middleware
        $middleware = new CheckTrustedDevice($trustedService);
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        // Should allow request to proceed
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_middleware_rejects_invalid_cookie()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create request with invalid cookie
        $request = Request::create('/test-route', 'GET');
        $request->cookies->set('trusted_device', 'invalid_cookie_data');

        $trustedService = new TrustedDeviceService();
        $middleware = new CheckTrustedDevice($trustedService);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        // Should reject the request
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_middleware_rejects_expired_device()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create expired trusted device
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => password_hash('test_token', PASSWORD_DEFAULT),
            'device_name' => 'Test Device',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
            'expires_at' => now()->subDays(1), // Expired yesterday
            'is_active' => true,
        ]);

        $payload = base64_encode($device->id . '|' . 'test_token');
        $request = Request::create('/test-route', 'GET');
        $request->cookies->set('trusted_device', $payload);

        $trustedService = new TrustedDeviceService();
        $middleware = new CheckTrustedDevice($trustedService);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        // Should reject expired device
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_middleware_rejects_inactive_device()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create inactive trusted device
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => password_hash('test_token', PASSWORD_DEFAULT),
            'device_name' => 'Test Device',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
            'expires_at' => now()->addDays(30),
            'is_active' => false, // Inactive
        ]);

        $payload = base64_encode($device->id . '|' . 'test_token');
        $request = Request::create('/test-route', 'GET');
        $request->cookies->set('trusted_device', $payload);

        $trustedService = new TrustedDeviceService();
        $middleware = new CheckTrustedDevice($trustedService);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        // Should reject inactive device
        $this->assertEquals(401, $response->getStatusCode());
    }
}
