<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\HeaderIntelligenceService;
use App\Services\RiskEngine;

beforeEach(function () {
    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

test('aligned headers add no score', function () {
    $result = HeaderIntelligenceService::analyze([
        'from_name' => 'Jane Doe',
        'reply_to' => 'jane@partner.com',
        'return_path' => 'bounce@partner.com',
    ], 'partner.com');

    expect($result['score'])->toBe(0);
    expect($result['findings'])->toBeEmpty();
});

test('reply-to on a different domain raises the score', function () {
    $result = HeaderIntelligenceService::analyze([
        'reply_to' => 'attacker@evil.com',
    ], 'partner.com');

    expect($result['score'])->toBe(25);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'Reply-To')))->toBeTrue();
});

test('return-path mismatch raises the score', function () {
    $result = HeaderIntelligenceService::analyze([
        'return_path' => 'bounce@spoofed.com',
    ], 'partner.com');

    expect($result['score'])->toBe(15);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'Return-Path')))->toBeTrue();
});

test('display name impersonating a different address is flagged', function () {
    $result = HeaderIntelligenceService::analyze([
        'from_name' => 'CEO <ceo@company.com>',
    ], 'staff-mail.com');

    expect($result['score'])->toBe(30);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'Display name impersonates')))->toBeTrue();
});

test('header score is capped at 50', function () {
    $result = HeaderIntelligenceService::analyze([
        'from_name' => 'Boss <boss@company.com>',
        'reply_to' => 'attacker@evil.com',
        'return_path' => 'bounce@spoofed.com',
    ], 'partner.com');

    // 30 + 25 + 15 = 70, capped to 50.
    expect($result['score'])->toBe(50);
});

test('header spoofing flows through the risk engine', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $benign = RiskEngine::evaluate([
        'sender_email' => 'hello@partner.com',
        'email_content' => 'Are you free to catch up?',
    ], $this->org->id);

    $spoofed = RiskEngine::evaluate([
        'sender_email' => 'hello@partner.com',
        'email_content' => 'Are you free to catch up?',
        'headers' => ['reply_to' => 'attacker@evil.com'],
    ], $this->org->id);

    expect($spoofed['risk_score'])->toBeGreaterThan($benign['risk_score']);
    expect(collect($spoofed['findings'])->contains(fn ($f) => str_contains($f, 'Reply-To')))->toBeTrue();
});

test('a trusted email with no headers stays safe', function () {
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
});
