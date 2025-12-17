<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Restaurant;
use App\Models\User;

class Payout extends Model
{
    protected $table = 'payouts';

    protected $fillable = [
        'restaurant_id',
        'requested_by',
        'external_reference',
        'amount',
        'currency',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'paid_at',
        'payment_provider',
        'provider_response',
        'is_active',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
        'provider_response' => 'array',
        'is_active' => 'boolean',
    ];

    // relations
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // helper
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
