<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    'must_change_password',
    'temporary_password',
])]
#[Hidden([
    'password',
    'remember_token',
    'temporary_password',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Human label for a user. Personal accounts (workers) have a first/last
     * name; an organization's founding account has none and falls back to its
     * `name` (the organization name), then email. Use this everywhere a user is
     * displayed so both kinds render correctly.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function () {
            $personal = trim("{$this->first_name} {$this->last_name}");

            return $personal !== '' ? $personal : ($this->name ?: $this->email);
        });
    }

    /**
     * Up-to-two-letter avatar initials derived from the same source as
     * displayName(), so a nameless org account shows its initials (e.g. "AG"),
     * never a blank circle.
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $parts = array_values(array_filter(
                preg_split('/\s+/', trim((string) $this->display_name)) ?: []
            ));

            if ($parts === []) {
                return strtoupper(mb_substr((string) $this->email, 0, 1)) ?: '?';
            }

            $first = mb_substr($parts[0], 0, 1);
            $second = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

            return strtoupper($first.$second);
        });
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
            'must_change_password' => 'boolean',
            'temporary_password' => 'encrypted',
        ];
    }

    /**
     * True while the account still holds a readable, system-issued temporary
     * password that the user has not yet replaced. This is the gate the admin
     * dashboard uses to decide whether to surface the credential.
     */
    public function hasPendingTemporaryPassword(): bool
    {
        return $this->must_change_password && filled($this->temporary_password);
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
