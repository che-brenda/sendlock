<?php

namespace App\Services;

use App\Services\Dns\DnsResolver;

/**
 * MX-record signal. A legitimate mail domain publishes MX records; their absence
 * on a domain that is trying to send you mail is suspicious. Uses the configured
 * DnsResolver driver, so it is inert (unknown) offline / in tests.
 */
class MxIntelligenceService
{
    public static function analyze(string $domain): array
    {
        $hasMx = app(DnsResolver::class)->hasMxRecords($domain);

        $score = 0;
        $findings = [];

        if ($hasMx === false) {
            $score = 10;
            $findings[] = 'Domain publishes no MX records (cannot receive mail — often disposable/spoofed)';
        }

        return [
            'score' => $score,
            'findings' => $findings,
            'signals' => ['mx_valid' => $hasMx],
        ];
    }
}
