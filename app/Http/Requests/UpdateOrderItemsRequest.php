<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    /**
     * ✅ SECURE - Added strict file validation
     */
    public function rules(): array
    {
        return [
            // We only allow updating items, notes, and voice notes here.
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.menu_item_id'    => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.qty'             => ['required', 'integer', 'min:0', 'max:20'], // 0 => remove
            
            // ✅ SECURE - Per-item voice note validation
            'items.*.voice_note'      => [
                'nullable',
                'file',
                'mimes:mp3,m4a,wav,ogg,mpeg,mpga',  // Audio only
                'max:5120',  // 5MB max
            ],

            'notes'                   => ['nullable', 'string', 'max:500'],
            
            // ✅ SECURE - Order-level voice note with strict validation
            'voice_note'              => [
                'nullable',
                'file',
                'mimes:mp3,m4a,wav,ogg,mpeg,mpga',  // Audio only
                'max:5120',  // 5MB max
            ],
            
            'clear_order_voice_note'  => ['sometimes', 'boolean'], // true to remove existing
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