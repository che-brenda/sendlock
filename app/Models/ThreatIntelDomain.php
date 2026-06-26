<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreatIntelDomain extends Model
{
    protected $fillable = [
        'domain',
        'category',
        'severity',
        'notes',
    ];
}
