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
        'subscription_status',
        'subscribed_at',
        'subscription_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'subscribed_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
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

    public function emailScans()
    {
        return $this->hasMany(EmailScan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Whether the organization is still awaiting its first payment. The billing
     * gate (EnsureSubscribed) holds these orgs at the billing page. Pre-existing
     * orgs have a null status and are never gated.
     */
    public function subscriptionPending(): bool
    {
        return $this->subscription_status === 'pending';
    }

    public function subscriptionActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    /** A paid subscription within this many days of expiry is "expiring soon". */
    public const EXPIRING_SOON_DAYS = 7;

    /**
     * Whole days until the paid subscription expires (negative once past, null
     * for Free / no expiry).
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->subscription_expires_at) {
            return null;
        }

        return (int) ceil(now()->floatDiffInDays($this->subscription_expires_at, false));
    }

    /** A paid subscription whose renewal date has passed. */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isPast();
    }

    /**
     * Expiry-aware billing health, the single source of truth for what an org
     * sees when it checks its subscription:
     * pending | free | active | expiring_soon | expired | none.
     * (`subscription_status` alone is not enough — a paid plan can be `active`
     * in the column yet past its renewal date.)
     */
    public function subscriptionState(): string
    {
        if ($this->subscriptionPending()) {
            return 'pending';
        }

        if (! $this->subscriptionActive()) {
            return 'none';
        }

        if (strtolower((string) $this->subscription_plan) === 'free') {
            return 'free';
        }

        if ($this->subscription_expires_at === null) {
            return 'active';
        }

        if ($this->isSubscriptionExpired()) {
            return 'expired';
        }

        return $this->daysUntilExpiry() <= self::EXPIRING_SOON_DAYS ? 'expiring_soon' : 'active';
    }

    /**
     * Human-facing package name for the org's current plan (e.g. plan `pro`
     * → "Professional"), resolved from config('sendlock.billing.packages').
     * Falls back to the capitalized plan key.
     */
    public function planLabel(): string
    {
        $plan = strtolower((string) $this->subscription_plan);

        foreach ((array) config('sendlock.billing.packages', []) as $package) {
            if (($package['plan'] ?? null) === $plan) {
                return $package['name'];
            }
        }

        return ucfirst($plan ?: 'Free');
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
