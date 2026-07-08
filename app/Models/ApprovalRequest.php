<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_RELEASED = 'RELEASED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'organization_id',
        'user_id',
        'email_scan_id',
        'recipient_email',
        'subject',
        'email_content',
        'risk_score',
        'risk_level',
        'decision',
        'confidence',
        'recommendations',
        'status',
        'requires_verification',
        'requires_approval',
        'recipient_verified_at',
        'released_at',
        'sent_at',
    ];

    protected $casts = [
        'recommendations' => 'array',
        'requires_verification' => 'boolean',
        'requires_approval' => 'boolean',
        'recipient_verified_at' => 'datetime',
        'released_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifications()
    {
        return $this->hasMany(RecipientVerification::class);
    }

    public function actions()
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_RELEASED,
            self::STATUS_REJECTED,
            self::STATUS_BLOCKED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Whether this send cleared BOTH recipient verification AND manager approval
     * and is released — the precondition for offering to add its domain/address
     * to the trusted database.
     */
    public function wasVerifiedAndApproved(): bool
    {
        return $this->status === self::STATUS_RELEASED
            && $this->recipient_verified_at !== null
            && $this->actions()->where('action', ApprovalAction::ACTION_APPROVED)->exists();
    }
}
