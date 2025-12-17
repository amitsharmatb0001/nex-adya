<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PAID      = 'paid';
    const STATUS_PREPARING = 'preparing';
    const STATUS_OUT       = 'out-for-delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // NOTE: allow notes & voice_note_path for mass-assign.
    protected $fillable = [
        'user_id','restaurant_id','total','status',
        'address_id','payment_id','notes','voice_note_path',
        'last_notified_status'
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function items()      { return $this->hasMany(OrderItem::class); }
}
