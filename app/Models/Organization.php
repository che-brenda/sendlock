<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'organization_name',
        'industry',
        'email',
        'phone',
        'subscription_plan',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}