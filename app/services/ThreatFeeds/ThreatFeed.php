<?php

namespace App\Services\ThreatFeeds;

/**
 * A single external threat-intelligence source (Google Safe Browsing, VirusTotal,
 * …). Implementations must be resilient: a network/credential failure yields a
 * null verdict (treated as "clean/unknown"), never an exception — a feed outage
 * must not break a scan.
 */
interface ThreatFeed
{
    /**
     * Stable identifier used in config and to label findings, e.g. "virustotal".
     */
    public function key(): string;

    /**
     * Whether this feed is usable (its API key is configured).
     */
    public function enabled(): bool;

    /**
     * Look up a domain. Returns a verdict array
     * ['severity' => 'HIGH'|'MEDIUM'|'LOW', 'category' => string] when the domain
     * is flagged, or null when the domain is clean / the lookup could not run.
     *
     * @return array{severity:string, category:string}|null
     */
    public function lookup(string $domain): ?array;
}
