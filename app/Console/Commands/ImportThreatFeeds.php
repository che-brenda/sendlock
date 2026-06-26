<?php

namespace App\Console\Commands;

use App\Models\ThreatIntelCache;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Imports bulk phishing-URL feeds (OpenPhish, PhishTank) into `threat_intel_cache`
 * so flagged domains are matched offline at scan time. Opt-in via
 * `SENDLOCK_THREAT_LISTS` — a no-op when nothing is enabled, so it never fetches
 * without being configured (safe to schedule unconditionally).
 */
class ImportThreatFeeds extends Command
{
    protected $signature = 'sendlock:import-threat-feeds';

    protected $description = 'Import phishing URL feeds (OpenPhish, PhishTank) into the threat-intel cache';

    public function handle(): int
    {
        $enabled = (array) config('sendlock.threat_feeds.lists.enabled', []);

        if ($enabled === []) {
            $this->info('No list feeds enabled (set SENDLOCK_THREAT_LISTS). Nothing to import.');

            return self::SUCCESS;
        }

        $imported = 0;

        if (in_array('openphish', $enabled, true)) {
            $imported += $this->importOpenPhish();
        }

        if (in_array('phishtank', $enabled, true)) {
            $imported += $this->importPhishTank();
        }

        $this->info("Imported {$imported} phishing domain(s) into the threat-intel cache.");

        return self::SUCCESS;
    }

    private function importOpenPhish(): int
    {
        try {
            $response = Http::timeout(30)->get((string) config('sendlock.threat_feeds.lists.openphish_url'));
        } catch (\Throwable $e) {
            $this->warn('OpenPhish fetch failed: '.$e->getMessage());

            return 0;
        }

        if (! $response->successful()) {
            $this->warn('OpenPhish fetch returned '.$response->status());

            return 0;
        }

        $urls = preg_split('/\r\n|\r|\n/', trim($response->body())) ?: [];

        return $this->storeDomains($urls, 'openphish');
    }

    private function importPhishTank(): int
    {
        $url = (string) config('sendlock.threat_feeds.lists.phishtank_url');
        $key = config('sendlock.threat_feeds.lists.phishtank_key');

        if (! empty($key)) {
            $url .= '?api_key='.$key;
        }

        try {
            $response = Http::timeout(60)->get($url);
        } catch (\Throwable $e) {
            $this->warn('PhishTank fetch failed: '.$e->getMessage());

            return 0;
        }

        if (! $response->successful()) {
            $this->warn('PhishTank fetch returned '.$response->status());

            return 0;
        }

        $urls = collect((array) $response->json())
            ->map(fn ($row) => is_array($row) ? ($row['url'] ?? null) : null)
            ->filter()
            ->all();

        return $this->storeDomains($urls, 'phishtank');
    }

    /**
     * Upsert the registrable host of each URL as a HIGH-severity phishing entry.
     *
     * @param  array<int,?string>  $urls
     */
    private function storeDomains(array $urls, string $source): int
    {
        $ttl = (int) config('sendlock.threat_feeds.cache_ttl', 720);
        $expires = Carbon::now()->addMinutes($ttl);
        $count = 0;

        foreach ($urls as $url) {
            $domain = $this->domainOf((string) $url);

            if ($domain === null) {
                continue;
            }

            ThreatIntelCache::updateOrCreate(
                ['domain' => $domain],
                [
                    'is_threat' => true,
                    'severity' => 'HIGH',
                    'category' => 'phishing',
                    'source' => $source,
                    'expires_at' => $expires,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function domainOf(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = strtolower(preg_replace('~^www\.~', '', (string) $host));

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host) ? $host : null;
    }
}
