<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifiedRecipient extends Model
{
    protected $fillable = [
        'organization_id',
        'email',
        'name',
        'phone',
        'verified',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
