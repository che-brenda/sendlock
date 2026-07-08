<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'reference',
        'package',
        'plan',
        'amount',
        'currency',
        'billing_cycle',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
