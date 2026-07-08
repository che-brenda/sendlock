<?php

use App\Models\Organization;
use App\Models\VerifiedRecipient;
use App\Services\RiskEngine;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);

    // The verified trusted contact from the reported scenario.
    VerifiedRecipient::create([
        'organization_id' => $this->org->id,
        'email' => 'chebrenda93@gmail.com',
        'verified' => true,
        'verified_at' => now(),
    ]);
});

test('an exact verified contact is trusted even on a consumer domain', function () {
    $result = RiskEngine::evaluate(['sender_email' => 'chebrenda93@gmail.com'], $this->org->id);

    expect($result['signals']['is_trusted_recipient'])->toBeTrue()
        ->and($result['signals']['contact_impersonation'])->toBeNull()
        ->and($result['risk_level'])->toBe('SAFE')
        ->and($result['decision'])->toBe('ALLOW');

    $addressRow = collect($result['analysis']['rows'])->firstWhere('key', 'trusted_address');
    expect($addressRow['value'])->toBe('Verified contact')->and($addressRow['status'])->toBe('ok');

    // The public-provider check is still run and recorded as a pass.
    $providerRow = collect($result['analysis']['rows'])->firstWhere('key', 'provider');
    expect($providerRow['value'])->toContain('Public provider')->and($providerRow['status'])->toBe('ok');
});

test('a single-letter change surfaces an "address not found — did you mean" suggestion', function () {
    // a -> n in the local part.
    $result = RiskEngine::evaluate(['sender_email' => 'chebrendn93@gmail.com'], $this->org->id);

    expect($result['signals']['is_trusted_recipient'])->toBeFalse()
        ->and($result['signals']['suggested_contact'])->toBe('chebrenda93@gmail.com')
        ->and($result['analysis']['suggestion'])->toBe('chebrenda93@gmail.com')
        ->and($result['risk_score'])->toBeGreaterThanOrEqual(70);

    $addressRow = collect($result['analysis']['rows'])->firstWhere('key', 'trusted_address');
    expect($addressRow['status'])->toBe('bad');
});

test('two legitimately-distinct verified contacts are both trusted, not flagged against each other', function () {
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'jone@partner.com', 'verified' => true, 'verified_at' => now()]);
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'joan@partner.com', 'verified' => true, 'verified_at' => now()]);

    foreach (['jone@partner.com', 'joan@partner.com'] as $address) {
        $result = RiskEngine::evaluate(['sender_email' => $address], $this->org->id);

        expect($result['signals']['is_trusted_recipient'])->toBeTrue()
            ->and($result['signals']['contact_impersonation'])->toBeNull()
            ->and($result['analysis']['suggestion'])->toBeNull();
    }
});

test('the suggestion is the closest verified contact', function () {
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'joan@partner.com', 'verified' => true, 'verified_at' => now()]);
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'jane@partner.com', 'verified' => true, 'verified_at' => now()]);

    // "jone@" is 1 edit from jane@... actually 1 from "jane"? jone->jane = 1 (o->a). jone->joan = 2. Closest = jane.
    $result = RiskEngine::evaluate(['sender_email' => 'jone@partner.com'], $this->org->id);

    expect($result['analysis']['suggestion'])->toBe('jane@partner.com');
});

test('a tampered domain on a trusted contact is caught as impersonation', function () {
    // gmail -> gmial (transposition) on the same local part.
    $result = RiskEngine::evaluate(['sender_email' => 'chebrenda93@gmial.com'], $this->org->id);

    expect($result['signals']['contact_impersonation'])->toBe('chebrenda93@gmail.com')
        ->and($result['risk_score'])->toBeGreaterThanOrEqual(70);
});

test('an unrelated address on the same domain is not treated as impersonation', function () {
    $result = RiskEngine::evaluate(['sender_email' => 'marketing-team@gmail.com'], $this->org->id);

    expect($result['signals']['contact_impersonation'])->toBeNull()
        ->and($result['signals']['is_trusted_recipient'])->toBeFalse();
});

test('entering a full email in the trusted-domain field trusts the address, not the domain', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->post(route('trust-center.trusted-domains.store'), [
            'domain' => 'newcontact@outlook.com',
            'vendor_name' => 'New Contact',
        ])
        ->assertRedirect();

    // Stored as a verified contact...
    $this->assertDatabaseHas('verified_recipients', [
        'organization_id' => $this->org->id,
        'email' => 'newcontact@outlook.com',
        'verified' => true,
    ]);

    // ...and NOT as a blanket trusted domain.
    $this->assertDatabaseMissing('trusted_domains', [
        'organization_id' => $this->org->id,
        'domain' => 'outlook.com',
    ]);
});

test('the lookalike is flagged end-to-end with a suggestion on the scan page', function () {
    $user = makeUser($this->org, 'Employee');

    $response = $this->actingAs($user)->post(route('email-scans.analyze'), [
        'sender_email' => 'chebrendn93@gmail.com',
    ]);

    $this->actingAs($user)
        ->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('Address not found in your verified contacts')
        ->assertSee('chebrenda93@gmail.com');
});
