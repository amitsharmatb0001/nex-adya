<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRestaurantsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Required to avoid “show all cities”
            'city'       => ['required','string','max:120'],

            // Optional filters
            'q'          => ['nullable','string','max:120'],                // name search
            'cuisine'    => ['nullable'],                                   // string or array
            'quick_in'   => ['nullable','integer','in:15,20,25,30,35,40'],  // “Quick in 30”
            'sort'       => ['nullable','in:trending,eta,new'],             // sort modes

            // Pagination
            'page'       => ['nullable','integer','min:1'],
            'size'       => ['nullable','integer','min:1','max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Support comma string for cuisine -> array
        $cuisine = $this->input('cuisine');
        if (is_string($cuisine) && str_contains($cuisine, ',')) {
            $this->merge(['cuisine' => array_filter(array_map('trim', explode(',', $cuisine)))]);
        }
    }
}
