<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [

        'organization_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'ip_address',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Restrict a query to the audit logs a given user is allowed to see:
     *
     * - Super Admin (product owner): every subscribed organization's logs.
     * - Organization / Head Organization Admin: the whole organization's logs
     *   (a head-org admin also sees its sub-organizations' logs).
     * - Everyone else (Manager, Employee, Security Officer, Auditor): only the
     *   logs of actions they themselves performed.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isOrgAdmin() || $user->isHeadOrgAdmin()) {
            $org = $user->organization;
            $ids = $org ? $org->descendantIds() : [$user->organization_id];

            return $query->whereIn('organization_id', $ids);
        }

        return $query
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id);
    }
}
