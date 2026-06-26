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
        'status',
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

    /**
     * Whether this organization's plan entitles it to a feature (e.g.
     * 'ai_classification', 'sms_verification'). Plan → feature mapping lives in
     * config('sendlock.plans'); this is the gate that prevents paid providers
     * from firing for non-entitled tenants. See config/sendlock.php.
     */
    public function hasFeature(string $feature): bool
    {
        $default = (string) config('sendlock.default_plan', 'free');
        $plan = strtolower((string) ($this->subscription_plan ?: $default));

        $features = (array) config(
            "sendlock.plans.{$plan}",
            config("sendlock.plans.{$default}", [])
        );

        return in_array('*', $features, true) || in_array($feature, $features, true);
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
