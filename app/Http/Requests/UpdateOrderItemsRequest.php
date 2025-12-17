<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // We only allow updating items, notes, and voice notes here.
            'items'                   => ['required','array','min:1'],
            'items.*.menu_item_id'    => ['required','integer','exists:menu_items,id'],
            'items.*.qty'             => ['required','integer','min:0','max:20'], // 0 => remove
            'items.*.voice_note'      => ['nullable','file','mimes:mp3,wav','max:10240'],

            'notes'                   => ['nullable','string','max:500'],
            'voice_note'              => ['nullable','file','mimes:mp3,wav','max:10240'], // order-level
            'clear_order_voice_note'  => ['sometimes','boolean'], // true to remove existing
        ];
    }
}
