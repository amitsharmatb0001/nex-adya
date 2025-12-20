<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * ✅ P3 FIX #15: Authentication Flow Tests
 * 
 * SAVE AS: tests/Feature/AuthenticationTest.php
 * 
 * Tests user registration, login, and logout flows.
 * Run with: php artisan test --filter=AuthenticationTest
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: User can register with valid data
     */
    public function test_user_can_register_successfully()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'phone' => '9876543210'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'ok',
                     'data' => [
                         'token',
                         'user' => ['id', 'name', 'email']
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);
    }

    /**
     * Test: Registration fails with duplicate email
     */
    public function test_cannot_register_with_duplicate_email()
    {
        // Create existing user
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'existing@example.com', // Duplicate email
            'password' => 'Password123',
            'password_confirmation' => 'Password123'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test: Registration requires password confirmation
     */
    public function test_registration_requires_password_confirmation()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'DifferentPassword' // Mismatch
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test: User can login with correct credentials
     */
    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123'
        ]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'ok',
                     'data' => ['token', 'user']
                 ]);
    }

    /**
     * Test: Login fails with incorrect password
     */
    public function test_login_fails_with_incorrect_password()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct_password')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error.message', 'Invalid credentials');
    }

    /**
     * Test: Login fails with non-existent email
     */
    public function test_login_fails_with_nonexistent_email()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error.message', 'Invalid credentials');
    }

    /**
     * Test: Authenticated user can access protected routes
     */
    public function test_authenticated_user_can_access_protected_routes()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk()
                 ->assertJsonStructure([
                     'ok',
                     'data' => ['id', 'name', 'email']
                 ])
                 ->assertJsonPath('data.email', $user->email);
    }

    /**
     * Test: Unauthenticated user cannot access protected routes
     */
    public function test_unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401); // Unauthorized
    }

    /**
     * Test: User can logout
     */
    public function test_user_can_logout()
    {
        $user = User::factory()->create();

        // Login first
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password' // Default factory password
        ]);

        $token = $loginResponse->json('data.token');

        // Logout
        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/auth/logout');

        $response->assertOk();

        // Verify token is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth' // Default token name
        ]);
    }

    /**
     * Test: Multiple failed login attempts are logged
     */
    public function test_failed_login_attempts_are_logged()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct_password')
        ]);

        // Attempt 3 failed logins
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'user@example.com',
                'password' => 'wrong_password'
            ]);
        }

        // Check activity logs (if enabled)
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'login_failed'
        ]);
    }

    /**
     * Test: User data returned on successful login
     */
    public function test_login_returns_correct_user_data()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123'
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.user.name', 'John Doe')
                 ->assertJsonPath('data.user.email', 'john@example.com')
                 ->assertJsonPath('data.user.id', $user->id);
    }
}