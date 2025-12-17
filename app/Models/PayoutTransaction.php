<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutTransaction extends Model
{
    protected $fillable = [
        'payout_id','kind','amount','running_balance','provider_data','reference','reconciled'
    ];

    protected $casts = [
        'provider_data' => 'array',
        'reconciled' => 'boolean',
    ];

    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }
}
