<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Restaurant, Menu, MenuItem, Order, OrderItem, Review, RestaurantOwner, ReviewDeletionRequest};
use Illuminate\Support\Facades\Notification;

class ReviewSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $restaurant;
    protected $menu;
    protected $menuItem;
    protected $restaurantOwner;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->restaurant = Restaurant::factory()->create();
        $this->menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->menuItem = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        
        // Create restaurant owner
        $ownerUser = User::factory()->create(['role' => 'restaurant']);
        $this->restaurantOwner = RestaurantOwner::factory()->create([
            'user_id' => $ownerUser->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function user_cannot_review_restaurant_without_ordering()
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/reviews", [
                'rating' => 5,
                'comment' => 'Great food!'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'NOT_VERIFIED_PURCHASE');
    }

    /** @test */
    public function user_cannot_review_menu_item_without_ordering()
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/menu-items/{$this->menuItem->id}/reviews", [
                'rating' => 4,
                'comment' => 'Tasty item!'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'NOT_VERIFIED_PURCHASE');
    }

    /** @test */
    public function user_can_review_restaurant_after_ordering()
    {
        // Create a delivered order
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'delivered'
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/reviews", [
                'rating' => 5,
                'comment' => 'Great food!'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Great food!');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 5
        ]);
    }

    /** @test */
    public function user_can_review_menu_item_after_ordering()
    {
        // Create a delivered order with menu item
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'delivered'
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/menu-items/{$this->menuItem->id}/reviews", [
                'rating' => 4,
                'comment' => 'Tasty item!'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $this->menuItem->id,
            'reviewable_type' => MenuItem::class,
            'rating' => 4
        ]);
    }

    /** @test */
    public function review_creation_sends_notification_to_restaurant_owners()
    {
        Notification::fake();

        // Create a delivered order
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'delivered'
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/restaurants/{$this->restaurant->id}/reviews", [
                'rating' => 5,
                'comment' => 'Great food!'
            ]);

        // Verify notification was sent to restaurant owner
        Notification::assertSentTo(
            $this->restaurantOwner->user,
            \App\Notifications\NewReviewNotification::class
        );
    }

    /** @test */
    public function restaurant_owner_can_request_review_deletion()
    {
        // Create a review first
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 1,
            'comment' => 'Terrible service!'
        ]);

        $ownerToken = $this->restaurantOwner->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->postJson("/api/v2/reviews/{$review->id}/request-deletion", [
                'reason' => 'Hate speech and inappropriate language',
                'evidence' => 'User used offensive language in review'
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('review_deletion_requests', [
            'review_id' => $review->id,
            'owner_id' => $this->restaurantOwner->id,
            'reason' => 'Hate speech and inappropriate language',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function restaurant_owner_cannot_request_deletion_for_other_restaurant_review()
    {
        // Create another restaurant and review
        $otherRestaurant = Restaurant::factory()->create();
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $otherRestaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 2,
            'comment' => 'Bad service'
        ]);

        $ownerToken = $this->restaurantOwner->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->postJson("/api/v2/reviews/{$review->id}/request-deletion", [
                'reason' => 'Hate speech',
                'evidence' => 'Offensive language'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Not your restaurant review');
    }

    /** @test */
    public function admin_can_view_review_deletion_requests()
    {
        // Create a deletion request
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 2,
            'comment' => 'Bad service'
        ]);

        $deletionRequest = ReviewDeletionRequest::create([
            'review_id' => $review->id,
            'owner_id' => $this->restaurantOwner->id,
            'reason' => 'Test reason',
            'status' => 'pending'
        ]);

        $adminToken = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/v3/review-deletion-requests');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data');
    }

    /** @test */
    public function admin_can_approve_review_deletion_request()
    {
        // Create a deletion request
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 2,
            'comment' => 'Bad service'
        ]);

        $deletionRequest = ReviewDeletionRequest::create([
            'review_id' => $review->id,
            'owner_id' => $this->restaurantOwner->id,
            'reason' => 'Test reason',
            'status' => 'pending'
        ]);

        $adminToken = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/v3/review-deletion-requests/{$deletionRequest->id}/approve", [
                'admin_notes' => 'Review contains hate speech, approved for deletion'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'approved');

        // Verify review was deleted
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /** @test */
    public function admin_can_reject_review_deletion_request()
    {
        // Create a deletion request
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 2,
            'comment' => 'Bad service'
        ]);

        $deletionRequest = ReviewDeletionRequest::create([
            'review_id' => $review->id,
            'owner_id' => $this->restaurantOwner->id,
            'reason' => 'Test reason',
            'status' => 'pending'
        ]);

        $adminToken = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/v3/review-deletion-requests/{$deletionRequest->id}/reject", [
                'admin_notes' => 'Review does not contain hate speech, request rejected'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'rejected');

        // Verify review still exists
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
        $this->assertDatabaseHas('review_deletion_requests', [
            'id' => $deletionRequest->id,
            'status' => 'rejected',
            'admin_id' => $this->admin->id
        ]);
    }

    /** @test */
    public function user_can_update_own_review()
    {
        // Create a delivered order and review
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'delivered'
        ]);

        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 3,
            'comment' => 'Average food'
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 5,
                'comment' => 'Actually, great food!'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Actually, great food!');
    }

    /** @test */
    public function user_can_delete_own_review()
    {
        // Create a delivered order and review
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'delivered'
        ]);

        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_id' => $this->restaurant->id,
            'reviewable_type' => Restaurant::class,
            'rating' => 4,
            'comment' => 'Good food'
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
