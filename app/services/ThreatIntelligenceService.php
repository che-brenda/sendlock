<?php

namespace App\Services;

use App\Models\ThreatIntelDomain;

/**
 * Domain reputation against the platform-wide threat intelligence list. A match
 * raises risk by severity. The list is DB-backed today (curated by Super Admins);
 * a real external feed is wired in behind the same method later.
 */
class ThreatIntelligenceService
{
    public static function analyze(string $domain): array
    {
        $domain = strtolower(trim($domain));

        $entry = ThreatIntelDomain::where('domain', $domain)->first();

        if ($entry === null) {
            return ['score' => 0, 'findings' => []];
        }

        $score = match (strtoupper((string) $entry->severity)) {
            'HIGH' => 70,
            'LOW' => 20,
            default => 40,
        };

        $label = $entry->category ? strtolower($entry->category) : 'threat';

        return [
            'score' => $score,
            'findings' => ['Domain flagged by threat intelligence ('.$label.', '.strtolower((string) $entry->severity).' severity)'],
        ];
    }
}
