<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'label'    => ['nullable','string','max:50'],
            'line1'    => ['required','string','max:255'],
            'line2'    => ['nullable','string','max:255'],
            'landmark' => ['nullable','string','max:255'],
            'city'     => ['required','string','max:120'],
            'state'    => ['nullable','string','max:120'],
            'pincode'  => ['required','string','max:12'],
            'lat'      => ['nullable','numeric','between:-90,90'],
            'lng'      => ['nullable','numeric','between:-180,180'],
            'is_default' => ['sometimes','boolean'],
        ];
    }
}
