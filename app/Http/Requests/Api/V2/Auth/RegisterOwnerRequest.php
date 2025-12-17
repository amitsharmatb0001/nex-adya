<?php

namespace App\Http\Requests\Api\V2\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level middleware controls access if needed
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            // Strong password policy: min 12, upper, lower, digit, special char
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[A-Z]/',       // uppercase
                'regex:/[a-z]/',       // lowercase
                'regex:/[0-9]/',       // digit
                'regex:/[\W_]/',       // special character
            ],
            'restaurant_id' => ['required','integer','exists:restaurants,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must include uppercase, lowercase, number and special character.',
        ];
    }
}
