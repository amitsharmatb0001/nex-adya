<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // scope: active | history | all (default: active)
            'scope' => ['sometimes','in:active,history,all'],
            // pagination
            'page'  => ['sometimes','integer','min:1'],
            'size'  => ['sometimes','integer','min:1','max:50'],
        ];
    }

    public function scope(): string
    {
        return $this->input('scope', 'active');
    }

    public function size(): int
    {
        return (int) $this->input('size', 20);
    }
}
