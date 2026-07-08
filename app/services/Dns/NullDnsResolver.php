<?php

namespace App\Services\Dns;

/**
 * No-op resolver — every check is "unknown". The default in tests (and any
 * environment without outbound DNS), so nothing hits the network.
 */
class NullDnsResolver implements DnsResolver
{
    public function hasMxRecords(string $domain): ?bool
    {
        return null;
    }
}
