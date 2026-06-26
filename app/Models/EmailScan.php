<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailScan extends Model
{
    protected $fillable = [

        'organization_id',
        'user_id',
        'sender_email',
        'sender_domain',
        'subject',
        'email_content',
        'risk_score',
        'risk_level',
        'decision',
        'confidence',
        'recommendations',
        'findings',
        'is_trusted_domain',
        'is_blocked_domain',
        'spf_pass',
        'dkim_pass',
        'dmarc_pass',

    ];

    protected $casts = [

        'findings' => 'array',
        'recommendations' => 'array',

        'is_trusted_domain' => 'boolean',
        'is_blocked_domain' => 'boolean',

        'spf_pass' => 'boolean',
        'dkim_pass' => 'boolean',
        'dmarc_pass' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
