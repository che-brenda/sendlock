<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;   // record is write-once

    protected $fillable = [
        'rule',
        'ip_address',
        'method',
        'path',
        'user_agent',
        'user_id',
        'organization_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
