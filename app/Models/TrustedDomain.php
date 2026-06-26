<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDomain extends Model
{
    protected $fillable = [

        'organization_id',
        'domain',
        'vendor_name',
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


