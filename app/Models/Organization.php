<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'parent_id',
        'organization_name',
        'type',
        'industry',
        'email',
        'phone',
        'subscription_plan',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    /**
     * The head organization this sub-organization belongs to.
     */
    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * Direct sub-organizations owned by this head organization.
     */
    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function isHead(): bool
    {
        return is_null($this->parent_id);
    }

    public function isSub(): bool
    {
        return ! $this->isHead();
    }

    /**
     * IDs of this organization plus every sub-organization beneath it.
     * Used to scope queries for head-organization admins across their tree.
     */
    public function descendantIds(): array
    {
        return collect([$this->id])
            ->merge($this->children()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }
}
