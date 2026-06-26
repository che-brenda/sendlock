<?php

namespace App\Services;

use App\Models\FlaggedDomain;
use Illuminate\Support\Carbon;

/**
 * Persists the impersonation / untrusted domains that {@see DomainIntelligenceService}
 * detects. The first sighting creates the row; every later sighting bumps
 * `times_seen` and the last-seen markers — which is what lets the UI warn a user
 * the second (and subsequent) time they try to use a flagged domain.
 *
 * This only records; it never blocks. Promoting a flagged domain to the curated
 * blocklist remains an admin decision in the Trust Center.
 */
class FlaggedDomainService
{
    /**
     * Detection types ordered most → least severe, for choosing a primary type
     * when a domain trips more than one heuristic.
     */
    private const SEVERITY = [
        'lookalike' => 8,
        'homograph' => 7,
        'typosquat' => 6,
        'subdomain_abuse' => 5,
        'disposable' => 4,
        'suspicious_tld' => 3,
        'entropy' => 2,
        'untrusted' => 1,
    ];

    /**
     * Record a detected domain. Returns the stored model plus whether this domain
     * had already been flagged before this call (`repeat`), or null when there is
     * nothing to record.
     *
     * @param  array<int,array{type:string,reason:string,resembles:?string}>  $flags
     * @return array{flagged:FlaggedDomain,repeat:bool}|null
     */
    public static function record(string $domain, array $flags, int $organizationId, ?int $userId): ?array
    {
        $domain = strtolower(trim($domain));

        if ($domain === '' || $flags === []) {
            return null;
        }

        [$type, $resembles] = self::primaryDetection($flags);

        $reason = collect($flags)
            ->pluck('reason')
            ->filter()
            ->unique()
            ->implode('; ');

        $existing = FlaggedDomain::where('organization_id', $organizationId)
            ->where('domain', $domain)
            ->first();

        $now = Carbon::now();

        if ($existing !== null) {
            $existing->fill([
                'detection_type' => $type,
                'reason' => $reason,
                'resembles' => $resembles,
                'times_seen' => $existing->times_seen + 1,
                'last_seen_at' => $now,
                'last_seen_by' => $userId,
            ])->save();

            return ['flagged' => $existing, 'repeat' => true];
        }

        $flagged = FlaggedDomain::create([
            'organization_id' => $organizationId,
            'domain' => $domain,
            'detection_type' => $type,
            'reason' => $reason,
            'resembles' => $resembles,
            'times_seen' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'last_seen_by' => $userId,
        ]);

        return ['flagged' => $flagged, 'repeat' => false];
    }

    /**
     * Pick the most severe detection as the primary type for the row.
     *
     * @param  array<int,array{type:string,reason:string,resembles:?string}>  $flags
     * @return array{0:string,1:?string} [type, resembles]
     */
    private static function primaryDetection(array $flags): array
    {
        $best = null;

        foreach ($flags as $flag) {
            $rank = self::SEVERITY[$flag['type']] ?? 0;

            if ($best === null || $rank > (self::SEVERITY[$best['type']] ?? 0)) {
                $best = $flag;
            }
        }

        return [$best['type'] ?? 'untrusted', $best['resembles'] ?? null];
    }
}
