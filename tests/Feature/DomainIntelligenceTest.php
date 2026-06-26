<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\DomainIntelligenceService;

beforeEach(function () {
    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

function flagTypes(array $result): array
{
    return collect($result['signals']['domain_flags'])->pluck('type')->all();
}

test('a confusable-character typosquat of a brand is detected', function () {
    // paypa1 -> paypal after de-obfuscation.
    $result = DomainIntelligenceService::analyze('paypa1.com', $this->org->id);

    expect(flagTypes($result))->toContain('typosquat');
    expect(collect($result['signals']['domain_flags'])
        ->firstWhere('type', 'typosquat')['resembles'])->toBe('paypal');
});

test('a near-miss misspelling of a brand is detected by edit distance', function () {
    $result = DomainIntelligenceService::analyze('gooogle.com', $this->org->id);

    expect(flagTypes($result))->toContain('typosquat');
});

test('a punycode / IDN domain is flagged as a homograph', function () {
    $result = DomainIntelligenceService::analyze('xn--pypal-4ve.com', $this->org->id);

    expect(flagTypes($result))->toContain('homograph');
});

test('a brand used as a subdomain of an unrelated domain is flagged', function () {
    $result = DomainIntelligenceService::analyze('paypal.secure-login.com', $this->org->id);

    expect(flagTypes($result))->toContain('subdomain_abuse');
});

test('a disposable mail domain is flagged', function () {
    $result = DomainIntelligenceService::analyze('mailinator.com', $this->org->id);

    expect(flagTypes($result))->toContain('disposable');
});

test('a high-risk TLD is flagged', function () {
    $result = DomainIntelligenceService::analyze('promo.xyz', $this->org->id);

    expect(flagTypes($result))->toContain('suspicious_tld');
});

test('a random-looking domain is flagged', function () {
    $result = DomainIntelligenceService::analyze('xkfqmzrbplt.com', $this->org->id);

    expect(flagTypes($result))->toContain('entropy');
});

test('a trusted domain trips no impersonation heuristics', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'paypal.com',
        'active' => true,
    ]);

    $result = DomainIntelligenceService::analyze('paypal.com', $this->org->id);

    expect($result['score'])->toBe(0);
    expect($result['signals']['domain_flags'])->toBeEmpty();
    expect($result['signals']['is_trusted_domain'])->toBeTrue();
});

test('an ordinary multi-word vendor domain is not mistaken for random', function () {
    $result = DomainIntelligenceService::analyze('unknown-vendor.com', $this->org->id);

    // Untrusted, yes — but not entropy/typosquat/homograph false positives.
    expect(flagTypes($result))->toContain('untrusted');
    expect(flagTypes($result))->not->toContain('entropy');
    expect(flagTypes($result))->not->toContain('typosquat');
    expect(flagTypes($result))->not->toContain('homograph');
});
