<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'rating',
        'eta_minutes',
        'cuisine',
        'city',
        'state',       // ✅ new column
        'is_active',
    ];

    protected $casts = [
        'rating'    => 'float',
        'is_active' => 'boolean',
    ];

    // ------------------------------
    // Relationships
    // ------------------------------

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // ------------------------------
    // Scopes
    // ------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function wishlists()
    {
        return $this->morphMany(Wishlist::class, 'wishlistable');
    }

    public function owners()
    {
        return $this->hasMany(RestaurantOwner::class);
    }
}
