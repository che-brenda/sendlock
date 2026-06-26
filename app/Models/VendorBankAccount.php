<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorBankAccount extends Model
{
    protected $fillable = [
        'organization_id',
        'vendor_name',
        'vendor_domain',
        'account_number',
        'iban',
        'swift',
        'label',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
