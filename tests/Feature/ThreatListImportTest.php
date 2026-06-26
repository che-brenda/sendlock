<?php

use App\Services\ThreatIntelligenceService;
use Illuminate\Support\Facades\Http;

test('the importer is a no-op when no list feeds are enabled', function () {
    Http::fake();

    $this->artisan('sendlock:import-threat-feeds')
        ->expectsOutputToContain('No list feeds enabled')
        ->assertSuccessful();

    Http::assertNothingSent();
    $this->assertDatabaseCount('threat_intel_cache', 0);
});

test('the OpenPhish feed is imported into the cache', function () {
    config(['sendlock.threat_feeds.lists.enabled' => ['openphish']]);

    Http::fake([
        'openphish.com/*' => Http::response(
            "https://phish-one.com/login\nhttp://www.phish-two.net/verify\nnot-a-url\n",
            200
        ),
    ]);

    $this->artisan('sendlock:import-threat-feeds')->assertSuccessful();

    $this->assertDatabaseHas('threat_intel_cache', [
        'domain' => 'phish-one.com', 'is_threat' => true, 'severity' => 'HIGH', 'source' => 'openphish',
    ]);
    $this->assertDatabaseHas('threat_intel_cache', ['domain' => 'phish-two.net', 'source' => 'openphish']);
    // The non-URL line is skipped.
    $this->assertDatabaseCount('threat_intel_cache', 2);
});

test('an imported domain is flagged by the threat intelligence service', function () {
    config(['sendlock.threat_feeds.lists.enabled' => ['openphish']]);

    Http::fake(['openphish.com/*' => Http::response("https://evil-phish.com/x\n", 200)]);

    $this->artisan('sendlock:import-threat-feeds')->assertSuccessful();

    $result = ThreatIntelligenceService::analyze('evil-phish.com');

    expect($result['score'])->toBe(70);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'phishing')))->toBeTrue();
});

test('the PhishTank JSON feed is imported', function () {
    config(['sendlock.threat_feeds.lists.enabled' => ['phishtank']]);

    Http::fake([
        'data.phishtank.com/*' => Http::response([
            ['url' => 'http://tank-phish.com/login'],
            ['url' => 'https://another-tank.org/verify'],
        ], 200),
    ]);

    $this->artisan('sendlock:import-threat-feeds')->assertSuccessful();

    $this->assertDatabaseHas('threat_intel_cache', ['domain' => 'tank-phish.com', 'source' => 'phishtank']);
    $this->assertDatabaseHas('threat_intel_cache', ['domain' => 'another-tank.org', 'source' => 'phishtank']);
});

test('a feed fetch failure does not crash the importer', function () {
    config(['sendlock.threat_feeds.lists.enabled' => ['openphish']]);

    Http::fake(['openphish.com/*' => Http::response('down', 500)]);

    $this->artisan('sendlock:import-threat-feeds')->assertSuccessful();

    $this->assertDatabaseCount('threat_intel_cache', 0);
});
