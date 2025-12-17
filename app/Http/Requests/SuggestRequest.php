<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // token is already required by route group
    }

    public function rules(): array
    {
        return [
            'city'  => ['required','string','max:100'],
            'q'     => ['required','string','min:1','max:50'],
            'size'  => ['sometimes','integer','min:1','max:20'],
            'quick' => ['sometimes','boolean'], // if 1 → restrict by eta_minutes<=30
        ];
    }

    // tiny helpers so controller stays clean
    public function city(): string   { return (string) $this->input('city'); }
    public function q(): string      { return trim((string) $this->input('q')); }
    public function size(): int      { return (int) $this->input('size', 10); }
    public function quick(): bool    { return (bool) $this->boolean('quick'); }
}
