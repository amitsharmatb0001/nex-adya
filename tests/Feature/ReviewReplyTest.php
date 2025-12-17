<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, Review, RestaurantOwner, ReviewReply};

class ReviewReplyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Review $review;
    protected RestaurantOwner $restaurantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a regular user who leaves a review
        $this->user = User::factory()->create(['role' => 'user']);

        // Create a restaurant
        $this->restaurant = Restaurant::factory()->create();

        // Create a review for the restaurant by the user
        $this->review = Review::factory()->create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
        ]);

        // Create a user for the restaurant owner
        $ownerUser = User::factory()->create(['role' => 'restaurant']);

        // Create the restaurant owner record, linking the user and restaurant
        $this->restaurantOwner = RestaurantOwner::factory()->create([
            'user_id' => $ownerUser->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function restaurant_owner_can_reply_to_a_review_for_their_restaurant()
    {
        $ownerUser = $this->restaurantOwner->user;

        $response = $this->actingAs($ownerUser, 'sanctum')
            ->postJson("/api/v2/reviews/{$this->review->id}/reply", [
                'reply' => 'Thank you for your valuable feedback!'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.reply', 'Thank you for your valuable feedback!');

        $this->assertDatabaseHas('review_replies', [
            'review_id' => $this->review->id,
            'owner_id' => $this->restaurantOwner->id,
            'reply' => 'Thank you for your valuable feedback!'
        ]);
    }

    /** @test */
    public function restaurant_owner_cannot_reply_to_review_for_another_restaurant()
    {
        // Create another restaurant and a review for it
        $otherRestaurant = Restaurant::factory()->create();
        $otherReview = Review::factory()->create([
            'reviewable_id' => $otherRestaurant->id,
            'reviewable_type' => Restaurant::class,
        ]);

        $ownerUser = $this->restaurantOwner->user;

        $response = $this->actingAs($ownerUser, 'sanctum')
            ->postJson("/api/v2/reviews/{$otherReview->id}/reply", [
                'reply' => 'Trying to reply to a review I do not own.'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Not your restaurant review');
    }

    /** @test */
    public function unauthenticated_user_cannot_reply_to_a_review()
    {
        $response = $this->postJson("/api/v2/reviews/{$this->review->id}/reply", [
            'reply' => 'I am a ghost.'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function regular_user_cannot_reply_to_a_review()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v2/reviews/{$this->review->id}/reply", [
                'reply' => 'I am just a customer.'
            ]);

        // The 'role:restaurant' middleware should return a 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function reply_field_is_required()
    {
        $ownerUser = $this->restaurantOwner->user;

        $response = $this->actingAs($ownerUser, 'sanctum')
            ->postJson("/api/v2/reviews/{$this->review->id}/reply", ['reply' => '']);

        $response->assertStatus(422)->assertJsonValidationErrors('reply');
    }
}