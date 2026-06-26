<?php

use App\Models\Organization;
use App\Models\ThreatIntelCache;
use App\Models\ThreatIntelDomain;
use App\Services\RiskEngine;
use App\Services\ThreatIntelligenceService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('no external call is made when no feed is enabled', function () {
    Http::fake();

    $result = ThreatIntelligenceService::analyze('clean-domain.com');

    expect($result['score'])->toBe(0);
    Http::assertNothingSent();
});

test('a virustotal malicious verdict flags the domain and is cached', function () {
    config(['sendlock.threat_feeds.enabled' => ['virustotal']]);
    config(['sendlock.threat_feeds.virustotal.key' => 'test-key']);

    Http::fake([
        'virustotal.com/*' => Http::response([
            'data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 5, 'suspicious' => 1]]],
        ], 200),
    ]);

    $result = ThreatIntelligenceService::analyze('evil-domain.com');

    expect($result['score'])->toBe(70);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'via virustotal')))->toBeTrue();

    $this->assertDatabaseHas('threat_intel_cache', [
        'domain' => 'evil-domain.com',
        'is_threat' => true,
        'severity' => 'HIGH',
    ]);
});

test('a cached verdict prevents a second external call', function () {
    config(['sendlock.threat_feeds.enabled' => ['virustotal']]);
    config(['sendlock.threat_feeds.virustotal.key' => 'test-key']);

    Http::fake([
        'virustotal.com/*' => Http::response([
            'data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 3]]],
        ], 200),
    ]);

    ThreatIntelligenceService::analyze('repeat-evil.com');
    ThreatIntelligenceService::analyze('repeat-evil.com');

    Http::assertSentCount(1);
});

test('a clean verdict is cached too so the feed is not re-queried', function () {
    config(['sendlock.threat_feeds.enabled' => ['virustotal']]);
    config(['sendlock.threat_feeds.virustotal.key' => 'test-key']);

    Http::fake([
        'virustotal.com/*' => Http::response([
            'data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 0, 'suspicious' => 0]]],
        ], 200),
    ]);

    expect(ThreatIntelligenceService::analyze('benign.com')['score'])->toBe(0);
    ThreatIntelligenceService::analyze('benign.com');

    Http::assertSentCount(1);
    $this->assertDatabaseHas('threat_intel_cache', ['domain' => 'benign.com', 'is_threat' => false]);
});

test('the curated platform list takes precedence over external feeds', function () {
    config(['sendlock.threat_feeds.enabled' => ['virustotal']]);
    config(['sendlock.threat_feeds.virustotal.key' => 'test-key']);
    Http::fake();

    ThreatIntelDomain::create(['domain' => 'known-bad.com', 'category' => 'phishing', 'severity' => 'HIGH']);

    $result = ThreatIntelligenceService::analyze('known-bad.com');

    expect($result['score'])->toBe(70);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'phishing')))->toBeTrue();
    Http::assertNothingSent(); // curated hit short-circuits before any feed call
});

test('a feed failure degrades gracefully to no score', function () {
    config(['sendlock.threat_feeds.enabled' => ['virustotal']]);
    config(['sendlock.threat_feeds.virustotal.key' => 'test-key']);

    Http::fake(['virustotal.com/*' => Http::response('error', 500)]);

    expect(ThreatIntelligenceService::analyze('unreachable.com')['score'])->toBe(0);
});

test('an external threat raises the overall risk engine verdict', function () {
    config(['sendlock.threat_feeds.enabled' => ['google_safe_browsing']]);
    config(['sendlock.threat_feeds.google_safe_browsing.key' => 'gsb-key']);

    Http::fake([
        'safebrowsing.googleapis.com/*' => Http::response([
            'matches' => [['threatType' => 'SOCIAL_ENGINEERING']],
        ], 200),
    ]);

    $result = RiskEngine::evaluate(['sender_email' => 'x@phish-site.com'], $this->org->id);

    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'phishing')))->toBeTrue();
    expect($result['risk_level'])->toBeIn(['HIGH', 'CRITICAL']);
});

test('caching avoids leaking a verdict across the unique domain key', function () {
    ThreatIntelCache::create([
        'domain' => 'manual.com',
        'is_threat' => true,
        'severity' => 'MEDIUM',
        'category' => 'test',
        'source' => 'virustotal',
        'expires_at' => now()->addHour(),
    ]);

    // A pre-seeded fresh cache row is honoured without any feed config.
    $result = ThreatIntelligenceService::analyze('manual.com');

    expect($result['score'])->toBe(40);
});
