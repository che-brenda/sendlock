<?php

namespace App\Services\Dns;

/**
 * Real DNS lookups via PHP's built-in resolver (no external API/key required).
 * Degrades to null (unknown) on any failure so a DNS hiccup never blocks a scan.
 */
class LiveDnsResolver implements DnsResolver
{
    public function hasMxRecords(string $domain): ?bool
    {
        $domain = trim($domain);

        if ($domain === '') {
            return null;
        }

        try {
            return checkdnsrr($domain, 'MX');
        } catch (\Throwable) {
            return null;
        }
    }
}
