<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // user must be authenticated; controller route has auth:sanctum
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'menu_item_id'  => ['nullable', 'integer', 'exists:menu_items,id'],
            'rating'        => ['required', 'integer', 'between:1,5'],
            'title'         => ['nullable', 'string', 'max:120'],
            'review'        => ['nullable', 'string', 'max:3000'],
            'is_anonymous'  => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        // coerce bool string -> bool (helpful for Postman/form-data)
        if ($this->has('is_anonymous')) {
            $this->merge(['is_anonymous' => filter_var($this->input('is_anonymous'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}
