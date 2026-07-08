<?php

namespace App\Services;

use App\Services\DomainAge\DomainAgeResolver;
use Illuminate\Support\Carbon;

/**
 * Domain-age signal. Newly-registered domains are a strong fraud indicator
 * (attackers register throwaway lookalikes days before a campaign). Uses the
 * configured DomainAgeResolver driver, so it is inert (unknown) until a provider
 * is enabled.
 */
class DomainAgeService
{
    /** Domains younger than this many days are treated as high-risk "very new". */
    public const VERY_NEW_DAYS = 30;

    public static function analyze(string $domain): array
    {
        $registeredAt = app(DomainAgeResolver::class)->registeredAt($domain);

        if (! $registeredAt instanceof Carbon) {
            return [
                'score' => 0,
                'findings' => [],
                'signals' => ['domain_age_days' => null],
            ];
        }

        $days = (int) floor($registeredAt->diffInDays(Carbon::now()));

        $score = 0;
        $findings = [];

        if ($days <= self::VERY_NEW_DAYS) {
            $score = 25;
            $findings[] = "Domain is only {$days} days old (very newly registered)";
        }

        return [
            'score' => $score,
            'findings' => $findings,
            'signals' => ['domain_age_days' => $days],
        ];
    }
}
