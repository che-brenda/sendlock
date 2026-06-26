<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedDomain extends Model
{
    protected $fillable = [

        'organization_id',
        'domain',
        'reason',
        'active'

    ];

    protected $casts = [

        'active' => 'boolean'

    ];

    public function organization()
    {
        return $this->belongsTo(
            Organization::class
        );
    }
}

