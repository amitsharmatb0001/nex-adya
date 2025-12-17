<?php

namespace App\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\RestaurantOwner;

class PayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restaurant_id' => [
                'required',
                'exists:restaurants,id',
                function ($attribute, $value, $fail) {
                    // Check if the current user is authorized to create payout for this restaurant
                    $user = $this->user();
                    if (!$user) {
                        $fail('User not authenticated.');
                        return;
                    }

                    $isOwner = RestaurantOwner::where('user_id', $user->id)
                        ->where('restaurant_id', $value)
                        ->where('is_active', true)
                        ->exists();

                    if (!$isOwner) {
                        $fail('You are not authorized to create payouts for this restaurant.');
                    }
                }
            ],
            'amount' => ['required','integer','min:1000','max:5000000'], // min ₹10 (1000 paise), max ₹50,000 (5000000 paise)
            'notes' => ['nullable','string','max:1000']
        ];
    }
}
