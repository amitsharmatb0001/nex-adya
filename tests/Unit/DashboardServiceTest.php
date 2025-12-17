<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\DashboardService;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Order;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\OrderItem;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_and_revenue()
    {
        // seed data
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();

        // create sample orders
        Order::factory()->count(3)->create(['restaurant_id' => $restaurant->id, 'total' => 10000, 'status' => 'pending', 'created_at' => now()]);
        Order::factory()->count(2)->create(['restaurant_id' => $restaurant->id, 'total' => 15000, 'status' => 'delivered', 'created_at' => now()]);

        $svc = new DashboardService();

        $overview = $svc->overview($restaurant->id);

        $this->assertArrayHasKey('today_revenue', $overview);
        $this->assertGreaterThanOrEqual(0, $overview['today_revenue']);
    }
}
