<?php

namespace App\Services\Dns;

/**
 * Resolves a domain's DNS posture for the risk engine. Kept behind a driver so
 * the offline test suite (and any air-gapped run) uses a null resolver, while
 * dev/production use real DNS lookups.
 */
interface DnsResolver
{
    /**
     * Whether the domain publishes MX records. Null = not checked / unknown
     * (contributes no score); true = valid MX; false = no MX (suspicious).
     */
    public function hasMxRecords(string $domain): ?bool;
}
