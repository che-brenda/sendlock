<?php

namespace App\Services\DomainAge;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Resolves domain age from RDAP (the modern, free, keyless successor to WHOIS)
 * via rdap.org, which redirects to the authoritative registry. Reads the
 * "registration" event date. Degrades to null on any error so a lookup failure
 * never blocks a scan. Enabled with SENDLOCK_DOMAIN_AGE_DRIVER=rdap.
 */
class RdapDomainAgeResolver implements DomainAgeResolver
{
    public function registeredAt(string $domain): ?Carbon
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return null;
        }

        try {
            $base = rtrim((string) config('sendlock.domain_age.rdap_url', 'https://rdap.org/domain/'), '/');

            $response = Http::timeout((int) config('sendlock.domain_age.timeout', 8))
                ->acceptJson()
                ->get($base.'/'.$domain);

            if (! $response->ok()) {
                return null;
            }

            foreach ((array) $response->json('events', []) as $event) {
                if (($event['eventAction'] ?? null) === 'registration' && ! empty($event['eventDate'])) {
                    return Carbon::parse($event['eventDate']);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
