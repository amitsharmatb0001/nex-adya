<?php

namespace App\Http\Requests\Api\V2\Menu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes','string','max:255'],
            'description' => ['nullable','string','max:1000'],
            'price' => ['sometimes','integer','min:0'],
            'veg' => ['sometimes','boolean'],
            'is_available' => ['sometimes','boolean'],
            'image' => ['nullable','file','mimes:jpg,jpeg,png','max:5120'],
        ];
    }
}
