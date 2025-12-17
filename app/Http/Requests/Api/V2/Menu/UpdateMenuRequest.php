<?php

namespace App\Http\Requests\Api\V2\Menu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes','string','max:255'],
            'description' => ['nullable','string','max:1000'],
            'is_active' => ['sometimes','boolean'],
        ];
    }
}
