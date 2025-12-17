<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is typically handled by middleware (e.g., auth:sanctum),
     * so we just return true here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define validation rules for the order creation request.
     *
     * Explanation for each rule:
     * - restaurant_id: must exist and reference a valid restaurant.
     * - address_id: must be supplied and an integer (required for delivery).
     * - items: must be a non-empty array (order cannot be empty).
     * - items.*.menu_item_id: every item must reference a valid menu item.
     * - items.*.qty: each quantity must be between 1 and 20 (max per order item).
     * - notes: optional, but if sent must be string and up to 500 chars.
     * - voice_note: optional, if sent must be an audio file (mp3 or wav, max 10MB).
     */
    public function rules(): array
    {
        return [
            // The ID of the restaurant where the order is placed.
            'restaurant_id'            => ['required', 'integer', 'exists:restaurants,id'],
            // Delivery address is mandatory. Without it, we can't fulfill the order.
            'address_id'               => ['required', 'integer', 'exists:addresses,id'],
            // The order must have at least one item.
            'items'                    => ['required', 'array', 'min:1'],
            // Each item needs a valid menu item ID and a quantity.
            'items.*.menu_item_id'     => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.qty'              => ['required', 'integer', 'min:1', 'max:20'],
            // Optional free-form notes for special instructions; does NOT affect price or logic.
            'notes'                    => ['nullable', 'string', 'max:500'],
            // Optional file upload for a voice note. Only accept mp3/wav, max size 10MB.
            'voice_note'               => ['nullable', 'file', 'mimes:mp3,wav', 'max:10240'],
            // Price fields should NOT be accepted from the client!
        ];
    }
}
