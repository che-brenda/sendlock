<?php

namespace App\Services\ThreatFeeds;

use Illuminate\Support\Facades\Http;

/**
 * VirusTotal domain report (API v3). Free tier is rate-limited (~4 req/min), so
 * results must be cached hard by the caller. Severity is derived from the
 * aggregate last-analysis stats: any malicious engine → HIGH, suspicious → MEDIUM.
 */
class VirusTotalFeed implements ThreatFeed
{
    private const ENDPOINT = 'https://www.virustotal.com/api/v3/domains/';

    public function key(): string
    {
        return 'virustotal';
    }

    public function enabled(): bool
    {
        return ! empty(config('sendlock.threat_feeds.virustotal.key'));
    }

    public function lookup(string $domain): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['x-apikey' => config('sendlock.threat_feeds.virustotal.key')])
                ->get(self::ENDPOINT.$domain);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $stats = $response->json('data.attributes.last_analysis_stats');

        if (! is_array($stats)) {
            return null;
        }

        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);

        if ($malicious > 0) {
            return ['severity' => 'HIGH', 'category' => 'malicious ('.$malicious.' engines)'];
        }

        if ($suspicious > 0) {
            return ['severity' => 'MEDIUM', 'category' => 'suspicious ('.$suspicious.' engines)'];
        }

        return null;
    }
}
