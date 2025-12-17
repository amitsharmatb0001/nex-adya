<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'payout_id', 'restaurant_id', 'user_id', 'type', 'amount', 'currency', 'reference', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }
}
