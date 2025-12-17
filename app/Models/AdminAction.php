<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAction extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'entity_type',
        'entity_id',
        'meta',
        'ip',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
