<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'name',
        'description',
        'price',          // stored in paise
        'veg',
        'rating',
        'image_url',
        'is_available',
        'voice_note_path',
    ];

    protected $casts = [
        'veg'          => 'boolean',
        'is_available' => 'boolean',
        'rating'       => 'decimal:2',
        'price'        => 'integer',
    ];

    public function menu()
    {
        // This was failing if App\Models\Menu didn't exist
        return $this->belongsTo(Menu::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function wishlists()
    {
        return $this->morphMany(Wishlist::class, 'wishlistable');
    }
    }
