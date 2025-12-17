<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'device_name', 'token_hash', 'raw_token_id', 'expires_at', 'last_used_at', 'ip_address', 'user_agent', 'is_active'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
