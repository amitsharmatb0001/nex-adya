<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedger extends Model
{
    protected $fillable = [
        'type',
        'source',
        'source_id',
        'amount',
        'user_id',
        'restaurant_id',
    ];
}
