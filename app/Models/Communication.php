<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    protected $fillable = [
        'organization_id',
        'counterpart_email',
        'counterpart_domain',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'occurrences' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
