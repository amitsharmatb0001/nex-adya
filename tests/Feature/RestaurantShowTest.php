<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{Restaurant, Menu, MenuItem};

class RestaurantShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_menus_with_available_items(): void
    {
        $r = Restaurant::factory()->create(['is_active' => true]);
        $menu = Menu::factory()->create(['restaurant_id' => $r->id]);

        // 3 available, 1 unavailable
        $i1 = MenuItem::factory()->create(['menu_id' => $menu->id, 'is_available' => true,  'rating' => 4.9]);
        $i2 = MenuItem::factory()->create(['menu_id' => $menu->id, 'is_available' => true,  'rating' => 4.7]);
        $i3 = MenuItem::factory()->create(['menu_id' => $menu->id, 'is_available' => true,  'rating' => 4.5]);
        $i4 = MenuItem::factory()->create(['menu_id' => $menu->id, 'is_available' => false, 'rating' => 5.0]);

        $this->getJson("/api/v1/restaurants/{$r->id}?items_limit=2")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $r->id)
            // ensure items array exists and respects limit (2) and availability
            ->assertJson(fn ($json) =>
                $json->where('data.menus.0.items.0.is_available', true)
                     ->where('data.menus.0.items.1.is_available', true)
                     ->etc()
            );
    }

    public function test_inactive_restaurant_returns_404(): void
    {
        $r = Restaurant::factory()->inactive()->create();

        $this->getJson("/api/v1/restaurants/{$r->id}")
            ->assertStatus(404);
    }
}
