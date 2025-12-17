<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
// Adjust if your model namespace differs
use App\Models\Restaurant;

class RestaurantListTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_city_and_supports_filters(): void
    {
        // Seed a few rows
        Restaurant::factory()->create(['city' => 'Delhi', 'cuisine' => 'Indian', 'rating' => 4.8, 'eta_minutes' => 25, 'name' => 'The Great Indian Kitchen']);
        Restaurant::factory()->create(['city' => 'Delhi', 'cuisine' => 'Italian','rating' => 4.6, 'eta_minutes' => 28, 'name' => 'Roman Holiday']);
        Restaurant::factory()->create(['city' => 'Mumbai','cuisine' => 'Indian', 'rating' => 4.9, 'eta_minutes' => 20, 'name' => 'Curry Kingdom']);

        // Missing city -> 422
        $this->getJson('/api/v1/restaurants')
            ->assertStatus(422);

        // City filter
        $this->getJson('/api/v1/restaurants?city=Delhi')
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Quick in 30
        $this->getJson('/api/v1/restaurants?city=Delhi&quick_in=30')
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Search + cuisine array
        $this->getJson('/api/v1/restaurants?city=Delhi&q=Indian&cuisine[]=Indian&sort=trending')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
