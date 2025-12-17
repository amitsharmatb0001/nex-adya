<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Address;
use App\Models\Order;
use App\Models\UserTwoFactorCode;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_backup_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all addresses for the user.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get all orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

/**
 * Get all two-factor codes for the user.
 */
public function twoFactorCodes()
{
    return $this->hasMany(UserTwoFactorCode::class);
}

public function isAdmin(): bool
{
    return $this->role === 'admin';
}

public function isRestaurant(): bool
{
    return $this->role === 'restaurant';
}   

public function isUser(): bool
{
    return $this->role === 'user';
}

public function isDelivery(): bool
{
    return $this->role === 'delivery';
}

/**
 * Get the delivery partner profile for this user.
 */
public function deliveryPartner()
{
    return $this->hasOne(DeliveryPartner::class);
}


}
