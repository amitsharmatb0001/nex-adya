<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayoutCreateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('createPayout', \App\Models\Payout::class); }

    public function rules(): array
    {
        return [
            'restaurant_id' => ['required','integer','exists:restaurants,id'],
            'amount' => ['required','integer','min:100'], // paise: minimum ₹1
            'currency' => ['sometimes','string','size:3'],
            'note' => ['nullable','string','max:500'],
            'metadata' => ['nullable','array'],
        ];
    }
}
