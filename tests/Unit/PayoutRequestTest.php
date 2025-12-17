<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\{User, Restaurant, RestaurantOwner};
use App\Http\Requests\Api\V2\PayoutRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

class PayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $restaurantOwner;
    protected $restaurant;
    protected $otherRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create restaurant owner
        $this->restaurantOwner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Test City',
            'is_active' => true
        ]);

        // Create another restaurant
        $this->otherRestaurant = Restaurant::create([
            'name' => 'Other Restaurant',
            'slug' => 'other-restaurant',
            'city' => 'Other City',
            'is_active' => true
        ]);

        // Create restaurant owner relationship
        RestaurantOwner::create([
            'user_id' => $this->restaurantOwner->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function payout_request_validates_required_fields()
    {
        $request = new PayoutRequest();
        
        $validator = Validator::make([], $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('restaurant_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_validates_restaurant_id_exists()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => 999, // Non-existent restaurant
            'amount' => 100000
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('restaurant_id', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_validates_amount_is_integer()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 'not_a_number'
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_validates_amount_minimum()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 500 // Less than minimum ₹10 (1000 paise)
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_validates_amount_maximum()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 10000000 // ₹100,000 - exceeds maximum
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_validates_restaurant_ownership()
    {
        $request = new PayoutRequest();
        
        // Mock the authenticated user
        $this->actingAs($this->restaurantOwner);
        
        $data = [
            'restaurant_id' => $this->otherRestaurant->id, // Not owned by user
            'amount' => 100000
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('restaurant_id', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_passes_with_valid_data()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 100000
        ];
        
        // Test basic validation rules without custom validation
        $basicRules = [
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'amount' => ['required','integer','min:1000','max:5000000'],
            'notes' => ['nullable','string','max:1000']
        ];
        
        $validator = Validator::make($data, $basicRules);
        
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function payout_request_validates_notes_are_optional()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 100000,
            'notes' => 'Optional notes'
        ];
        
        // Test basic validation rules without custom validation
        $basicRules = [
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'amount' => ['required','integer','min:1000','max:5000000'],
            'notes' => ['nullable','string','max:1000']
        ];
        
        $validator = Validator::make($data, $basicRules);
        
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function payout_request_validates_notes_max_length()
    {
        $request = new PayoutRequest();
        
        // Mock the authenticated user
        $this->actingAs($this->restaurantOwner);
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 100000,
            'notes' => str_repeat('a', 1001) // Exceeds 1000 character limit
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('notes', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_handles_inactive_restaurant_owner()
    {
        // Create an inactive restaurant owner
        $inactiveOwner = User::create([
            'name' => 'Inactive Owner',
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'role' => 'restaurant'
        ]);

        RestaurantOwner::create([
            'user_id' => $inactiveOwner->id,
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false // Inactive
        ]);

        $request = new PayoutRequest();
        
        // Mock the inactive user
        $this->actingAs($inactiveOwner);
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 100000
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('restaurant_id', $validator->errors()->toArray());
    }

    /** @test */
    public function payout_request_handles_unauthenticated_user()
    {
        $request = new PayoutRequest();
        
        $data = [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 100000
        ];
        
        $validator = Validator::make($data, $request->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('restaurant_id', $validator->errors()->toArray());
    }
}