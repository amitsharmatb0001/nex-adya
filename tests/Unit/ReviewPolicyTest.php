<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Review;
use App\Models\Restaurant;
use App\Models\RestaurantOwner;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ReviewPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewPolicy();
    }

    public function test_owner_can_update_review()
    {
        // Create user who wrote the review
        $user = User::factory()->create();
        
        // Create restaurant
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        // Create review
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that the user can update their own review
        $this->assertTrue($this->policy->update($user, $review));
    }

    public function test_non_owner_cannot_update_review()
    {
        // Create user who wrote the review
        $reviewAuthor = User::factory()->create();
        
        // Create different user trying to update
        $otherUser = User::factory()->create();
        
        // Create restaurant
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        // Create review by first user
        $review = Review::factory()->create([
            'user_id' => $reviewAuthor->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that other user cannot update the review
        $this->assertFalse($this->policy->update($otherUser, $review));
    }

    public function test_admin_can_delete_review()
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create regular user who wrote the review
        $user = User::factory()->create();
        
        // Create restaurant
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        // Create review
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that admin can delete any review
        $this->assertTrue($this->policy->delete($admin, $review));
    }

    public function test_restaurant_owner_cannot_delete_review()
    {
        // Create restaurant owner
        $owner = User::factory()->create(['role' => 'restaurant']);
        
        // Create restaurant
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        // Create restaurant owner relationship
        RestaurantOwner::factory()->create([
            'user_id' => $owner->id,
            'restaurant_id' => $restaurant->id,
            'is_active' => true
        ]);

        // Create review by different user
        $reviewer = User::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $reviewer->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 2,
            'comment' => 'Bad food'
        ]);

        // Test that restaurant owner cannot delete the review
        $this->assertFalse($this->policy->delete($owner, $review));
    }

    public function test_user_can_delete_own_review()
    {
        // Create user
        $user = User::factory()->create();
        
        // Create restaurant
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        // Create review by the user
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that user can delete their own review
        $this->assertTrue($this->policy->delete($user, $review));
    }

    public function test_guest_cannot_perform_any_actions()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that guest (null user) cannot perform any actions
        $this->assertFalse($this->policy->update(null, $review));
        $this->assertFalse($this->policy->delete(null, $review));
    }

    public function test_policy_with_null_review()
    {
        $user = User::factory()->create();

        // Test that policy handles null review gracefully
        $this->assertFalse($this->policy->update($user, null));
        $this->assertFalse($this->policy->delete($user, null));
    }

    public function test_view_policy()
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City'
        ]);

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_id' => $restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        // Test that anyone can view reviews
        $this->assertTrue($this->policy->view($user, $review));
        $this->assertTrue($this->policy->view(null, $review));
    }
}
