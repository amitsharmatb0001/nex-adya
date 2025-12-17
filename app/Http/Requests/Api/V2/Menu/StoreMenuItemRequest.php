<?php

namespace App\Http\Requests\Api\V2\Menu;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string','max:1000'],
            'price' => ['required','integer','min:0'], // paise
            'veg' => ['sometimes','boolean'],
            'is_available' => ['sometimes','boolean'],
            'image' => ['nullable','file','mimes:jpg,jpeg,png','max:5120'],
        ];
    }
}
