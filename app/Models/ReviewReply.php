<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReviewReply extends Model
{
    use HasFactory;

    protected $fillable = ['review_id','owner_id','reply'];

    public function review(): BelongsTo {
        return $this->belongsTo(Review::class);
    }

    public function owner(): BelongsTo  {
        return $this->belongsTo(RestaurantOwner::class, 'owner_id');
    }
}
