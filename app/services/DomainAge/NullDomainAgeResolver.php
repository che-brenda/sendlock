<?php

namespace App\Services\DomainAge;

use Illuminate\Support\Carbon;

/**
 * Default resolver — domain age is always unknown (no external lookups). Swap in
 * RdapDomainAgeResolver via SENDLOCK_DOMAIN_AGE_DRIVER=rdap to enable it.
 */
class NullDomainAgeResolver implements DomainAgeResolver
{
    public function registeredAt(string $domain): ?Carbon
    {
        return null;
    }
}
