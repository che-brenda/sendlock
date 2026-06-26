<?php

namespace App\Services\ThreatFeeds;

use Illuminate\Support\Facades\Http;

/**
 * Google Safe Browsing (Lookup API v4). Free with an API key, generous quota.
 * A threatMatch on the domain URL is treated as a HIGH-severity hit; the
 * threatType maps to a human category.
 */
class GoogleSafeBrowsingFeed implements ThreatFeed
{
    private const ENDPOINT = 'https://safebrowsing.googleapis.com/v4/threatMatches:find';

    public function key(): string
    {
        return 'google_safe_browsing';
    }

    public function enabled(): bool
    {
        return ! empty(config('sendlock.threat_feeds.google_safe_browsing.key'));
    }

    public function lookup(string $domain): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->post(self::ENDPOINT.'?key='.config('sendlock.threat_feeds.google_safe_browsing.key'), [
                    'client' => ['clientId' => 'sendlock', 'clientVersion' => '1.0'],
                    'threatInfo' => [
                        'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                        'platformTypes' => ['ANY_PLATFORM'],
                        'threatEntryTypes' => ['URL'],
                        'threatEntries' => [['url' => 'http://'.$domain]],
                    ],
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $matches = $response->json('matches');

        if (empty($matches)) {
            return null;
        }

        $threatType = (string) ($matches[0]['threatType'] ?? 'THREAT');

        return [
            'severity' => 'HIGH',
            'category' => match ($threatType) {
                'SOCIAL_ENGINEERING' => 'phishing',
                'MALWARE' => 'malware',
                'UNWANTED_SOFTWARE' => 'unwanted software',
                default => strtolower(str_replace('_', ' ', $threatType)),
            },
        ];
    }
}
