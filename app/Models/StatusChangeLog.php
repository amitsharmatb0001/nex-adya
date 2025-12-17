<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusChangeLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'from_status',
        'to_status',
        'changed_by',
        'role',
    ];
}
