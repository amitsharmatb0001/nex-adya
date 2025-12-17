<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ownership check is done in controller; this just ensures authenticated
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating'        => ['sometimes', 'integer', 'between:1,5'],
            'title'         => ['nullable', 'string', 'max:120'],
            'review'        => ['nullable', 'string', 'max:3000'],
            'is_anonymous'  => ['sometimes', 'boolean'],
            'is_visible'    => ['sometimes', 'boolean'], // admin/moderation
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('is_anonymous')) {
            $this->merge(['is_anonymous' => filter_var($this->input('is_anonymous'), FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('is_visible')) {
            $this->merge(['is_visible' => filter_var($this->input('is_visible'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}
