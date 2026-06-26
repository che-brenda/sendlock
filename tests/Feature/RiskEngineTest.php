<?php

use App\Models\BlockedDomain;
use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Models\VendorBankAccount;
use App\Services\RiskEngine;

beforeEach(function () {
    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

test('blocked domain produces a critical quarantine verdict', function () {
    BlockedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'fraud.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate(['sender_email' => 'attacker@fraud.com'], $this->org->id);

    expect($result['risk_score'])->toBe(100);
    expect($result['risk_level'])->toBe('CRITICAL');
    expect($result['decision'])->toBe('QUARANTINE');
    expect($result['signals']['is_blocked_domain'])->toBeTrue();
});

test('trusted domain with benign content is safe and allowed', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'hello@partner.com',
        'subject' => 'Lunch next week',
        'email_content' => 'Are you free to catch up?',
    ], $this->org->id);

    expect($result['risk_level'])->toBe('SAFE');
    expect($result['decision'])->toBe('ALLOW');
    expect($result['signals']['is_trusted_domain'])->toBeTrue();
});

test('unknown domain with bank-change language raises the score', function () {
    $result = RiskEngine::evaluate([
        'sender_email' => 'billing@some-vendor.com',
        'subject' => 'Change of bank account',
        'email_content' => 'Please update our bank account for the next wire transfer.',
    ], $this->org->id);

    // 70 (untrusted) + bank-change content signals -> HIGH or CRITICAL.
    expect($result['risk_score'])->toBeGreaterThanOrEqual(70);
    expect($result['risk_level'])->toBeIn(['HIGH', 'CRITICAL']);
});

test('an unknown domain with benign content is high risk on its own', function () {
    $result = RiskEngine::evaluate([
        'sender_email' => 'hello@some-new-vendor.com',
        'subject' => 'Quick question',
        'email_content' => 'Are you available for a call?',
    ], $this->org->id);

    expect($result['risk_score'])->toBeGreaterThanOrEqual(70);
    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision'])->toBe('RECIPIENT_VERIFY');
});

test('lookalike of a trusted domain is flagged', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'activa-assurances.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'claims@activa-assurance.com', // one char off
    ], $this->org->id);

    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'closely resembles')))->toBeTrue();
    expect($result['risk_score'])->toBeGreaterThanOrEqual(70);
});

test('banking details that differ from the known vendor account are critical', function () {
    VendorBankAccount::create([
        'organization_id' => $this->org->id,
        'vendor_domain' => 'supplier.com',
        'account_number' => '123456789',
    ]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'accounts@supplier.com',
        'subject' => 'New bank account',
        'email_content' => 'Please use our new bank account 987654321 for all future payments.',
    ], $this->org->id);

    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'differ from the known account')))->toBeTrue();
    expect($result['risk_level'])->toBeIn(['HIGH', 'CRITICAL']);
});

test('every verdict carries confidence and recommendations', function () {
    $result = RiskEngine::evaluate([
        'sender_email' => 'billing@unknown-vendor.com',
        'subject' => 'Change of bank account',
        'email_content' => 'Please update our bank account for the next wire transfer.',
    ], $this->org->id);

    expect($result)->toHaveKeys(['confidence', 'recommendations']);
    expect($result['confidence'])->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
    expect($result['recommendations'])->toBeArray()->not->toBeEmpty();
});

test('a trusted benign email is classified SAFE', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'hello@partner.com',
        'email_content' => 'Coffee tomorrow?',
    ], $this->org->id);

    expect($result['risk_level'])->toBe('SAFE');
    expect($result['decision'])->toBe('ALLOW');
    expect($result['confidence'])->toBeGreaterThanOrEqual(70);
});

test('more corroborating signals yield higher confidence', function () {
    VendorBankAccount::create([
        'organization_id' => $this->org->id,
        'vendor_domain' => 'supplier.com',
        'account_number' => '123456789',
    ]);

    // Untrusted + content + financial-mismatch signals all fire together.
    $manySignals = RiskEngine::evaluate([
        'sender_email' => 'accounts@supplier.com',
        'subject' => 'New bank account',
        'email_content' => 'Use our new bank account 987654321 for the urgent payment.',
    ], $this->org->id);

    // A single untrusted-domain heuristic.
    $oneSignal = RiskEngine::evaluate([
        'sender_email' => 'hi@some-vendor.com',
    ], $this->org->id);

    expect($manySignals['confidence'])->toBeGreaterThan($oneSignal['confidence']);
});

test('a quarantine verdict recommends not releasing', function () {
    BlockedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'fraud.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate(['sender_email' => 'x@fraud.com'], $this->org->id);

    expect($result['confidence'])->toBe(100);
    expect(collect($result['recommendations'])->contains(fn ($r) => str_contains($r, 'Do not release')))->toBeTrue();
});

test('the risk engine never scopes across organizations', function () {
    $other = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);

    // Domain trusted for the OTHER org should not count as trusted for ours.
    TrustedDomain::create([
        'organization_id' => $other->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $result = RiskEngine::evaluate(['sender_email' => 'hi@partner.com'], $this->org->id);

    expect($result['signals']['is_trusted_domain'])->toBeFalse();
});
