<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayoutApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // only admins can auto-approve in our design (or use policies)
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'provider_hint' => ['nullable','string'],
            'note' => ['nullable','string','max:500'],
        ];
    }
}
