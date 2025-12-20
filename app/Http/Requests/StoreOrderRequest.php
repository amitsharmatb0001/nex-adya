<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define validation rules for the order creation request.
     *
     * ✅ SECURE - Added strict file validation
     */
    public function rules(): array
    {
        return [
            // The ID of the restaurant where the order is placed.
            'restaurant_id'            => ['required', 'integer', 'exists:restaurants,id'],
            
            // Delivery address is mandatory.
            'address_id'               => ['required', 'integer', 'exists:addresses,id'],
            
            // The order must have at least one item.
            'items'                    => ['required', 'array', 'min:1'],
            
            // Each item needs a valid menu item ID and a quantity.
            'items.*.menu_item_id'     => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.qty'              => ['required', 'integer', 'min:1', 'max:20'],
            
            // ✅ SECURE - Per-item voice note validation
            'items.*.voice_note'       => [
                'nullable',
                'file',
                'mimes:mp3,m4a,wav,ogg,mpeg,mpga',  // Audio only
                'max:5120',  // 5MB max
            ],
            
            // Optional notes for special instructions.
            'notes'                    => ['nullable', 'string', 'max:500'],
            
            // ✅ SECURE - Order-level voice note with strict validation
            'voice_note'               => [
                'nullable',
                'file',
                'mimes:mp3,m4a,wav,ogg,mpeg,mpga',  // Audio only
                'max:5120',  // 5MB max
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'voice_note.mimes' => 'Voice note must be an audio file (mp3, m4a, wav, ogg).',
            'voice_note.max' => 'Voice note must not exceed 5MB.',
            'items.*.voice_note.mimes' => 'Item voice note must be an audio file (mp3, m4a, wav, ogg).',
            'items.*.voice_note.max' => 'Item voice note must not exceed 5MB.',
        ];
    }
}