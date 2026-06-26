<?php

namespace App\Services;

use App\Models\ThreatIntelCache;
use App\Models\ThreatIntelDomain;
use App\Services\ThreatFeeds\GoogleSafeBrowsingFeed;
use App\Services\ThreatFeeds\ThreatFeed;
use App\Services\ThreatFeeds\VirusTotalFeed;
use Illuminate\Support\Carbon;

/**
 * Domain reputation. The curated platform list (`ThreatIntelDomain`, managed by
 * Super Admins) is authoritative and checked first. For domains not on it, the
 * configured external feeds (Safe Browsing, VirusTotal…) are consulted and their
 * verdicts cached in `threat_intel_cache` to respect free-tier rate limits. With
 * no feeds enabled (the default) this falls back to the curated list alone.
 */
class ThreatIntelligenceService
{
    /**
     * Registry of available feeds, keyed by their config identifier.
     */
    private const FEEDS = [
        'google_safe_browsing' => GoogleSafeBrowsingFeed::class,
        'virustotal' => VirusTotalFeed::class,
    ];

    public static function analyze(string $domain): array
    {
        $domain = strtolower(trim($domain));

        // 1. Curated platform list — highest authority.
        $entry = ThreatIntelDomain::where('domain', $domain)->first();

        if ($entry !== null) {
            $severity = strtoupper((string) $entry->severity);
            $label = $entry->category ? strtolower($entry->category) : 'threat';

            return [
                'score' => self::severityScore($severity),
                'findings' => ['Domain flagged by threat intelligence ('.$label.', '.strtolower($severity).' severity)'],
            ];
        }

        // 2. External feeds (cached).
        $verdict = self::externalLookup($domain);

        if ($verdict === null) {
            return ['score' => 0, 'findings' => []];
        }

        return [
            'score' => self::severityScore($verdict['severity']),
            'findings' => ['Domain flagged by threat intelligence ('.$verdict['category'].', '.strtolower($verdict['severity']).' severity, via '.$verdict['source'].')'],
        ];
    }

    private static function severityScore(string $severity): int
    {
        return match (strtoupper($severity)) {
            'HIGH' => 70,
            'LOW' => 20,
            default => 40,
        };
    }

    /**
     * Resolve an external verdict for a domain: cache first, then live feeds.
     * Caches both threat and clean results. Returns null when no feed is enabled
     * or the domain is clean.
     *
     * @return array{severity:string, category:string, source:string}|null
     */
    private static function externalLookup(string $domain): ?array
    {
        $cached = ThreatIntelCache::where('domain', $domain)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($cached !== null) {
            return $cached->is_threat
                ? ['severity' => $cached->severity, 'category' => $cached->category, 'source' => $cached->source]
                : null;
        }

        $verdict = null;
        $queried = false;

        foreach (self::enabledFeeds() as $feed) {
            $queried = true;
            $hit = $feed->lookup($domain);

            if ($hit !== null) {
                $verdict = ['severity' => $hit['severity'], 'category' => $hit['category'], 'source' => $feed->key()];
                break;
            }
        }

        // No feed actually ran — don't cache a "clean" we never checked.
        if (! $queried) {
            return null;
        }

        self::store($domain, $verdict);

        return $verdict;
    }

    /**
     * @return ThreatFeed[]
     */
    private static function enabledFeeds(): array
    {
        $feeds = [];

        foreach ((array) config('sendlock.threat_feeds.enabled', []) as $key) {
            $class = self::FEEDS[$key] ?? null;

            if ($class === null) {
                continue;
            }

            $feed = new $class;

            if ($feed->enabled()) {
                $feeds[] = $feed;
            }
        }

        return $feeds;
    }

    private static function store(string $domain, ?array $verdict): void
    {
        $ttl = (int) config('sendlock.threat_feeds.cache_ttl', 720);

        ThreatIntelCache::updateOrCreate(
            ['domain' => $domain],
            [
                'is_threat' => $verdict !== null,
                'severity' => $verdict['severity'] ?? null,
                'category' => $verdict['category'] ?? null,
                'source' => $verdict['source'] ?? null,
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]
        );
    }
}
