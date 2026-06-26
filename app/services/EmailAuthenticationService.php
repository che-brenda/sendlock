<?php

namespace App\Services;

/**
 * SPF / DKIM / DMARC posture for a sender domain.
 *
 * Per-message results (from an inbound gateway's Authentication-Results header)
 * may be supplied explicitly and always win. Otherwise the configured driver is
 * consulted; the default "null" driver returns unknown (null) for every check,
 * which contributes no score. Only an explicit failure raises risk — absence of
 * data is treated as unknown, not as failure.
 */
class EmailAuthenticationService
{
    /**
     * @param  array{spf?:?bool, dkim?:?bool, dmarc?:?bool}  $explicit
     */
    public static function analyze(string $domain, array $explicit = []): array
    {
        $resolved = self::resolve($domain);

        $spf = array_key_exists('spf', $explicit) ? $explicit['spf'] : $resolved['spf'];
        $dkim = array_key_exists('dkim', $explicit) ? $explicit['dkim'] : $resolved['dkim'];
        $dmarc = array_key_exists('dmarc', $explicit) ? $explicit['dmarc'] : $resolved['dmarc'];

        $score = 0;
        $findings = [];

        if ($spf === false) {
            $score += 15;
            $findings[] = 'SPF validation failed';
        }

        if ($dkim === false) {
            $score += 10;
            $findings[] = 'DKIM validation failed';
        }

        if ($dmarc === false) {
            $score += 20;
            $findings[] = 'DMARC validation failed';
        }

        return [
            'score' => min($score, 35),
            'findings' => $findings,
            'signals' => [
                'spf_pass' => $spf,
                'dkim_pass' => $dkim,
                'dmarc_pass' => $dmarc,
            ],
        ];
    }

    /**
     * @return array{spf:?bool, dkim:?bool, dmarc:?bool}
     */
    private static function resolve(string $domain): array
    {
        return match (config('sendlock.email_auth.driver', 'null')) {
            // Real DNS/header drivers slot in here later.
            default => ['spf' => null, 'dkim' => null, 'dmarc' => null],
        };
    }
}
