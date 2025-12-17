<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'provider_payment_id',
        'status',
        'amount',
        'currency',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // Relations
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function order() {
        return $this->belongsTo(Order::class);
    }
}
