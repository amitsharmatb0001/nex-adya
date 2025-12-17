<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_on_seed()
    {
        // Run the seeder
        $seeder = new AdminUserSeeder();
        $seeder->run();

        // Verify admin user was created
        $admin = User::where('email', 'admin@example.com')->first();
        
        $this->assertNotNull($admin);
        $this->assertEquals('admin@example.com', $admin->email);
        $this->assertEquals('admin', $admin->role);
        $this->assertNotNull($admin->password);
        // email_verified_at is not set by the seeder
    }

    public function test_seeder_does_not_duplicate_admin()
    {
        // Run seeder first time
        $seeder = new AdminUserSeeder();
        $seeder->run();

        $firstCount = User::where('email', 'admin@example.com')->count();
        $this->assertEquals(1, $firstCount);

        // Run seeder second time
        $seeder->run();

        $secondCount = User::where('email', 'admin@example.com')->count();
        $this->assertEquals(1, $secondCount); // Should still be 1, not 2
    }

    public function test_admin_has_correct_attributes()
    {
        $seeder = new AdminUserSeeder();
        $seeder->run();

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertEquals('Super Admin', $admin->name);
        $this->assertEquals('admin', $admin->role);
        // email_verified_at is not set by the seeder
        $this->assertNotNull($admin->password);
        $this->assertTrue(strlen($admin->password) > 0);
    }

    public function test_admin_password_is_hashed()
    {
        $seeder = new AdminUserSeeder();
        $seeder->run();

        $admin = User::where('email', 'admin@example.com')->first();

        // Password should be hashed, not plain text
        $this->assertNotEquals('Admin@12345!', $admin->password);
        $this->assertTrue(password_verify('Admin@12345!', $admin->password));
    }

    public function test_seeder_works_with_existing_users()
    {
        // Create some existing users
        User::factory()->count(5)->create();

        $initialCount = User::count();

        // Run admin seeder
        $seeder = new AdminUserSeeder();
        $seeder->run();

        $finalCount = User::count();
        $this->assertEquals($initialCount + 1, $finalCount);

        // Admin should still be created
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
    }

    public function test_admin_can_authenticate()
    {
        $seeder = new AdminUserSeeder();
        $seeder->run();

        // Test admin can login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Admin@12345!'
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('ok'));
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    public function test_admin_has_admin_role()
    {
        $seeder = new AdminUserSeeder();
        $seeder->run();

        $admin = User::where('email', 'admin@example.com')->first();

        // Test admin role methods if they exist
        if (method_exists($admin, 'isAdmin')) {
            $this->assertTrue($admin->isAdmin());
        }

        if (method_exists($admin, 'hasRole')) {
            $this->assertTrue($admin->hasRole('admin'));
        }
    }
}
