<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewDeletionRequest extends Model
{
    protected $fillable = [
        'review_id',
        'owner_id',
        'reason',
        'evidence',
        'status',
        'admin_id',
        'admin_notes'
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(RestaurantOwner::class, 'owner_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
