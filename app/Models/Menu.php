<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = ['restaurant_id', 'title', 'description'];

    // NOTE: A menu belongs to a restaurant.
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // NOTE: A menu has many items.
    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }
}
