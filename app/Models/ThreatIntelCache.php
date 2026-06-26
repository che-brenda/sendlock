<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Normalized cache of external threat-feed verdicts (Safe Browsing, VirusTotal…),
 * keyed by domain. Stores both threat and clean verdicts so repeat lookups stay
 * inside free-tier rate limits. Platform-wide (not tenant-scoped) — domain
 * reputation is global. Rows expire via `expires_at`.
 */
class ThreatIntelCache extends Model
{
    protected $table = 'threat_intel_cache';

    protected $fillable = [
        'domain',
        'is_threat',
        'severity',
        'category',
        'source',
        'expires_at',
    ];

    protected $casts = [
        'is_threat' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
