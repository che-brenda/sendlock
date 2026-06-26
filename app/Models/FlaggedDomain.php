<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlaggedDomain extends Model
{
    protected $fillable = [

        'organization_id',
        'domain',
        'detection_type',
        'reason',
        'resembles',
        'times_seen',
        'first_seen_at',
        'last_seen_at',
        'last_seen_by',

    ];

    protected $casts = [

        'times_seen' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',

    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function lastSeenBy()
    {
        return $this->belongsTo(User::class, 'last_seen_by');
    }
}
