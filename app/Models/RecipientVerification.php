<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipientVerification extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'approval_request_id',
        'organization_id',
        'recipient_email',
        'recipient_phone',
        'channel',
        'code',
        'status',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'code',
    ];

    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
