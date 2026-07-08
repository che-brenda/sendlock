<?php

use App\Models\Communication;
use App\Models\EmailScan;
use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\CommunicationHistoryService;
use App\Services\Dns\DnsResolver;
use App\Services\DomainAge\DomainAgeResolver;
use App\Services\RiskEngine;
use App\Services\SensitiveDataService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);

    $this->user = makeUser($this->org, 'Employee');
});

// --- Communication Relationship Analysis -----------------------------------

test('communication history records and aggregates interactions', function () {
    CommunicationHistoryService::record($this->org->id, 'billing@vendor.com');
    CommunicationHistoryService::record($this->org->id, 'billing@vendor.com');
    CommunicationHistoryService::record($this->org->id, 'sales@vendor.com');

    expect(CommunicationHistoryService::forEmail($this->org->id, 'billing@vendor.com')['count'])->toBe(2)
        ->and(CommunicationHistoryService::forDomain($this->org->id, 'vendor.com')['count'])->toBe(3);

    // Scoped per organization.
    expect(Communication::where('organization_id', $this->org->id)->count())->toBe(2);
});

// --- Sensitive data / DLP ---------------------------------------------------

test('sensitive data service detects PII and financial identifiers', function () {
    $result = SensitiveDataService::analyze('Card 4242 4242 4242 4242, IBAN DE89370400440532013000, SSN 123-45-6789');

    expect($result['signals']['sensitive_data'])
        ->toContain('payment_card')
        ->toContain('bank_iban')
        ->toContain('gov_id');

    // Detection is score-neutral so it never distorts the threat verdict.
    expect($result['score'])->toBe(0);
});

test('sensitive data service is quiet on benign content', function () {
    $result = SensitiveDataService::analyze('Hello, please find the meeting notes attached. Thanks.');

    expect($result['signals']['sensitive_data'])->toBe([]);
});

// --- MX + domain-age signals (via faked drivers) ---------------------------

test('the engine folds in a faked MX driver', function () {
    app()->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function hasMxRecords(string $domain): ?bool
        {
            return false;
        }
    });

    $result = RiskEngine::evaluate(['sender_email' => 'x@no-mx-domain.com'], $this->org->id);

    $mxRow = collect($result['analysis']['rows'])->firstWhere('key', 'mx');
    expect($mxRow['value'])->toBe('Missing')->and($mxRow['status'])->toBe('bad');
});

test('the engine folds in a faked domain-age driver', function () {
    app()->instance(DomainAgeResolver::class, new class implements DomainAgeResolver
    {
        public function registeredAt(string $domain): ?Carbon
        {
            return Carbon::now()->subDays(12);
        }
    });

    $result = RiskEngine::evaluate(['sender_email' => 'x@brand-new.com'], $this->org->id);

    $ageRow = collect($result['analysis']['rows'])->firstWhere('key', 'domain_age');
    expect($ageRow['value'])->toContain('12 days')->and($ageRow['status'])->toBe('bad');
});

// --- Analysis breakdown -----------------------------------------------------

test('the risk verdict includes the full analysis breakdown', function () {
    $result = RiskEngine::evaluate(['sender_email' => 'someone@unknown-vendor.com'], $this->org->id);

    $keys = collect($result['analysis']['rows'])->pluck('key');

    expect($keys)->toContain('provider', 'trusted_address', 'domain_age', 'similarity', 'mx', 'spf', 'dmarc', 'reputation', 'previous_communication');

    // A never-seen sender has no prior communication.
    $prev = collect($result['analysis']['rows'])->firstWhere('key', 'previous_communication');
    expect($prev['value'])->toBe('None Found');
});

test('the provider check passes but the trust-list check fails for an unverified public-provider address', function () {
    $result = RiskEngine::evaluate(['sender_email' => 'random-person@gmail.com'], $this->org->id);
    $rows = collect($result['analysis']['rows']);

    // Public-provider test runs and PASSES (recognized)...
    expect($rows->firstWhere('key', 'provider')['status'])->toBe('ok')
        ->and($rows->firstWhere('key', 'provider')['value'])->toContain('Public provider');

    // ...but the address is not in the trust list, so that check FAILS and it gates.
    expect($rows->firstWhere('key', 'trusted_address')['status'])->toBe('bad')
        ->and($result['decision'])->toBeIn(['MANAGER_APPROVAL', 'RECIPIENT_VERIFY', 'QUARANTINE']);
});

test('a trusted domain passes the trust-list check and still runs every other check', function () {
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $result = RiskEngine::evaluate(['sender_email' => 'vendor@partner.com'], $this->org->id);
    $rows = collect($result['analysis']['rows']);

    expect($rows->firstWhere('key', 'trusted_address')['value'])->toBe('Trusted domain')
        ->and($rows->firstWhere('key', 'trusted_address')['status'])->toBe('ok');

    // Nothing is skipped — the other checks are still present and recorded.
    expect($rows->pluck('key'))->toContain('domain_age', 'mx', 'spf', 'dmarc', 'reputation', 'previous_communication');
});

test('a lookalike of a trusted domain surfaces the similar-trusted panel with history', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'activa-assurances.com',
        'active' => true,
    ]);

    // Seed prior legitimate communication with the real vendor domain.
    CommunicationHistoryService::record($this->org->id, 'claims@activa-assurances.com');
    CommunicationHistoryService::record($this->org->id, 'billing@activa-assurances.com');

    // One-character lookalike (…con instead of …com).
    $result = RiskEngine::evaluate(['sender_email' => 'claims@activa-assurances.con'], $this->org->id);

    $similar = $result['analysis']['similar_trusted'];
    expect($similar)->not->toBeNull()
        ->and($similar['domain'])->toBe('activa-assurances.com')
        ->and($similar['sample_email'])->toBe('claims@activa-assurances.com')
        ->and($similar['total'])->toBe(2);
});

// --- The page ---------------------------------------------------------------

test('scanning redirects to the risk analysis page and shows the breakdown', function () {
    $response = $this->actingAs($this->user)->post(route('email-scans.analyze'), [
        'sender_email' => 'someone@unknown-vendor.com',
        'subject' => 'Urgent payment',
        'email_content' => 'Please process this wire transfer urgently.',
    ]);

    $response->assertRedirect();

    $this->actingAs($this->user)
        ->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('Domain Risk Analysis')
        ->assertSee('Risk Score')
        ->assertSee('Previous Communication')
        ->assertSee('None Found')
        ->assertSee('someone@unknown-vendor.com');
});

test('previous communication reflects a repeat sighting on the next scan', function () {
    // First scan of this sender.
    $this->actingAs($this->user)->post(route('email-scans.analyze'), [
        'sender_email' => 'repeat@vendor-x.com',
    ]);

    // Second scan — now there is prior communication.
    $response = $this->actingAs($this->user)->post(route('email-scans.analyze'), [
        'sender_email' => 'repeat@vendor-x.com',
    ]);

    $this->actingAs($this->user)
        ->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('prior email');
});

test('the risk analysis sidebar shortcut opens the latest scan', function () {
    $this->actingAs($this->user)->post(route('email-scans.analyze'), ['sender_email' => 'a@one.com']);
    $this->actingAs($this->user)->post(route('email-scans.analyze'), ['sender_email' => 'b@two.com']);

    $latest = EmailScan::where('organization_id', $this->org->id)->latest()->first();

    $this->actingAs($this->user)
        ->get(route('risk-analysis'))
        ->assertRedirect(route('email-scans.show', $latest));
});

test('the risk analysis shortcut falls back to the scan form with no scans', function () {
    $this->actingAs($this->user)
        ->get(route('risk-analysis'))
        ->assertRedirect(route('email-scans.index'));
});

test('the analysis page is tenant scoped', function () {
    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $otherUser = makeUser($otherOrg, 'Employee');

    $response = $this->actingAs($otherUser)->post(route('email-scans.analyze'), [
        'sender_email' => 'x@foreign.com',
    ]);
    $location = $response->headers->get('Location');

    // A user from a different org cannot open that scan.
    $this->actingAs($this->user)->get($location)->assertNotFound();
});
