<?php

use App\Models\Organization;
use App\Models\ThreatIntelDomain;
use App\Services\RiskEngine;
use App\Services\UrlInspectionService;
use App\Services\AttachmentAnalysisService;
use App\Services\EmailAuthenticationService;

beforeEach(function () {
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('attachment analysis flags dangerous, macro and double-extension files', function () {
    expect(AttachmentAnalysisService::analyze(['invoice.exe'])['score'])->toBeGreaterThan(0);
    expect(AttachmentAnalysisService::analyze(['report.docm'])['score'])->toBeGreaterThan(0);

    $double = AttachmentAnalysisService::analyze(['invoice.pdf.exe']);
    expect(collect($double['findings'])->contains(fn ($f) => str_contains($f, 'double extension')))->toBeTrue();

    expect(AttachmentAnalysisService::analyze(['safe.pdf'])['score'])->toBe(0);
});

test('url inspection flags anchor mismatch, ip links and risky tlds', function () {
    $mismatch = UrlInspectionService::analyze('Login here [standardbank.com](http://standardbank-login.ru/auth)');
    expect(collect($mismatch['findings'])->contains(fn ($f) => str_contains($f, 'different domain')))->toBeTrue();

    $ip = UrlInspectionService::analyze('Click http://203.0.113.9/pay');
    expect(collect($ip['findings'])->contains(fn ($f) => str_contains($f, 'raw IP')))->toBeTrue();

    expect(UrlInspectionService::analyze('See https://example.com/info')['score'])->toBe(0);
});

test('email authentication only penalises explicit failures', function () {
    expect(EmailAuthenticationService::analyze('x.com')['score'])->toBe(0); // null driver = unknown

    $failed = EmailAuthenticationService::analyze('x.com', ['spf' => false, 'dmarc' => false]);
    expect($failed['score'])->toBeGreaterThan(0);
    expect($failed['signals']['spf_pass'])->toBeFalse();
});

test('a threat-intel listed domain drives the risk engine to critical', function () {
    ThreatIntelDomain::create(['domain' => 'evil.com', 'category' => 'phishing', 'severity' => 'HIGH']);

    $result = RiskEngine::evaluate(['sender_email' => 'x@evil.com'], $this->org->id);

    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'threat intelligence')))->toBeTrue();
    expect($result['risk_level'])->toBe('CRITICAL');
});

test('attachments and explicit auth failures raise the engine score', function () {
    $clean = RiskEngine::evaluate(['sender_email' => 'x@unknown-co.com'], $this->org->id);

    $loaded = RiskEngine::evaluate([
        'sender_email' => 'x@unknown-co.com',
        'attachments' => ['payload.exe'],
        'auth' => ['spf' => false],
    ], $this->org->id);

    expect($loaded['risk_score'])->toBeGreaterThan($clean['risk_score']);
});

test('a trusted benign email is unaffected by the new signals', function () {
    \App\Models\TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'hi@partner.com',
        'subject' => 'Hello',
        'email_content' => 'Just checking in, no links here.',
    ], $this->org->id);

    expect($result['risk_level'])->toBe('SAFE');
    expect($result['decision'])->toBe('ALLOW');
});
