<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'first_name',
    'last_name',
    'name',
    'job_title',
    'email',
    'phone',
    'password',
    'organization_id',
    'worker_number',
    'department_id',
    'status',
    'last_login',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Role helpers (single source of truth for access-level checks)
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function isHeadOrgAdmin(): bool
    {
        return $this->hasRole('Head Organization Admin');
    }

    public function isOrgAdmin(): bool
    {
        return $this->hasRole('Organization Admin');
    }

    /**
     * Sub-organization powers: a Super Admin, or any admin (Organization Admin /
     * Head Organization Admin) whose organization is a **head** organization.
     * Every top-level org admin can create, manage, and view the sub-orgs beneath
     * them — no separate role required. Sub-org admins (org type 'sub') don't
     * qualify, keeping the hierarchy two levels deep.
     */
    public function canManageSubOrganizations(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ($this->isOrgAdmin() || $this->isHeadOrgAdmin())
            && (bool) $this->organization?->isHead();
    }
}
