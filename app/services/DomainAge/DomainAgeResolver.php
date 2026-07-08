<?php

namespace App\Services\DomainAge;

use Illuminate\Support\Carbon;

/**
 * Resolves a domain's registration date. Behind a driver because it needs an
 * external source (WHOIS/RDAP); the default null resolver returns unknown, so
 * the feature is present but inert until a provider is configured.
 */
interface DomainAgeResolver
{
    /** Registration date of the domain, or null if unknown / unresolved. */
    public function registeredAt(string $domain): ?Carbon;
}
