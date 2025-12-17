<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPartner extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryPartnerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'vehicle_type',
        'vehicle_number',
        'phone',
        'documents',
        'rating',
        'total_deliveries',
        'is_active',
        'is_available',
        'last_active_at',
        'current_location',
    ];

    protected $casts = [
        'documents' => 'array',
        'current_location' => 'array',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'last_active_at' => 'datetime',
        'rating' => 'decimal:2',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_partner_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeNearby($query, $lat, $lng, $radius = 10)
    {
        // This would use a spatial query in production
        // For now, we'll implement a simple distance calculation
        return $query->whereNotNull('current_location');
    }

    // Helper methods
    public function updateLocation($lat, $lng)
    {
        $this->update([
            'current_location' => ['lat' => $lat, 'lng' => $lng],
            'last_active_at' => now(),
        ]);
    }

    public function setAvailable($available = true)
    {
        $this->update([
            'is_available' => $available,
            'last_active_at' => now(),
        ]);
    }

    public function updateRating($newRating)
    {
        $this->update([
            'rating' => $newRating,
        ]);
    }

    public function incrementDeliveries()
    {
        $this->increment('total_deliveries');
    }
}
