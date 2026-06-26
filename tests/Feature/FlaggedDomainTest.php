<?php

use App\Models\ApprovalRequest;
use App\Models\FlaggedDomain;
use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\FlaggedDomainService;
use App\Services\RiskEngine;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

test('the domain service records an untrusted domain on first sighting as not a repeat', function () {
    $flags = RiskEngine::evaluate(['sender_email' => 'x@unknown-vendor.com'], $this->org->id)['signals']['domain_flags'];

    $record = FlaggedDomainService::record('unknown-vendor.com', $flags, $this->org->id, null);

    expect($record['repeat'])->toBeFalse();
    expect($record['flagged']->detection_type)->toBe('untrusted');
    expect($record['flagged']->times_seen)->toBe(1);
});

test('a second sighting is reported as a repeat and bumps times_seen', function () {
    $flags = [['type' => 'untrusted', 'reason' => 'Domain not found in Trust Center', 'resembles' => null]];

    FlaggedDomainService::record('repeat.com', $flags, $this->org->id, null);
    $second = FlaggedDomainService::record('repeat.com', $flags, $this->org->id, null);

    expect($second['repeat'])->toBeTrue();
    expect($second['flagged']->times_seen)->toBe(2);
    expect(FlaggedDomain::where('domain', 'repeat.com')->count())->toBe(1);
});

test('a lookalike is recorded with its detection type and resembled domain', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'activa-assurances.com',
        'active' => true,
    ]);

    $flags = RiskEngine::evaluate(['sender_email' => 'x@activa-assurance.com'], $this->org->id)['signals']['domain_flags'];

    $record = FlaggedDomainService::record('activa-assurance.com', $flags, $this->org->id, null);

    expect($record['flagged']->detection_type)->toBe('lookalike');
    expect($record['flagged']->resembles)->toBe('activa-assurances.com');
});

test('scanning the same untrusted domain twice surfaces a popup warning the second time', function () {
    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->post(route('email-scans.analyze'), ['sender_email' => 'x@bad-vendor.com'])
        ->assertRedirect()
        ->assertSessionMissing('domain_warning');

    $this->actingAs($user)
        ->post(route('email-scans.analyze'), ['sender_email' => 'y@bad-vendor.com'])
        ->assertRedirect()
        ->assertSessionHas('domain_warning');
});

test('a homograph detection outranks the baseline untrusted flag', function () {
    $flags = RiskEngine::evaluate(['sender_email' => 'x@xn--pypal-4ve.com'], $this->org->id)['signals']['domain_flags'];

    $record = FlaggedDomainService::record('xn--pypal-4ve.com', $flags, $this->org->id, null);

    expect($record['flagged']->detection_type)->toBe('homograph');
});

test('a trusted benign domain is never flagged', function () {
    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)->post(route('email-scans.analyze'), ['sender_email' => 'hi@partner.com']);
    $this->actingAs($user)->post(route('email-scans.analyze'), ['sender_email' => 'hi@partner.com']);

    expect(FlaggedDomain::where('organization_id', $this->org->id)->count())->toBe(0);
});

test('a repeat flagged domain holds the protected send until acknowledged', function () {
    $user = makeUser($this->org, 'Employee');

    // First send records the domain and proceeds normally.
    $this->actingAs($user)
        ->post(route('protected-email.store'), ['recipient_email' => 'a@suspicious.com'])
        ->assertRedirect();

    expect(ApprovalRequest::where('organization_id', $this->org->id)->count())->toBe(1);

    // Second send is held behind the popup warning — no new request created.
    $this->actingAs($user)
        ->post(route('protected-email.store'), ['recipient_email' => 'b@suspicious.com'])
        ->assertSessionHas('domain_warning');

    expect(ApprovalRequest::where('organization_id', $this->org->id)->count())->toBe(1);

    // Acknowledging the warning (override) lets the send through.
    $this->actingAs($user)
        ->post(route('protected-email.store'), [
            'recipient_email' => 'b@suspicious.com',
            'acknowledged' => '1',
        ])
        ->assertRedirect();

    expect(ApprovalRequest::where('organization_id', $this->org->id)->count())->toBe(2);
});

test('requesting manager authorization from the popup creates a pending approval', function () {
    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->post(route('flagged-domains.request-approval'), [
            'recipient_email' => 'escalate@suspicious.com',
            'subject' => 'Invoice',
        ])
        ->assertRedirect();

    $request = ApprovalRequest::where('organization_id', $this->org->id)->first();

    expect($request)->not->toBeNull();
    expect($request->status)->not->toBe(ApprovalRequest::STATUS_RELEASED);
    expect($request->requires_approval)->toBeTrue();
});

test('flagged domains are scoped to the current organization', function () {
    $other = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);

    FlaggedDomain::create([
        'organization_id' => $other->id,
        'domain' => 'foreign-flagged.com',
        'detection_type' => 'untrusted',
        'times_seen' => 3,
    ]);

    $user = makeUser($this->org, 'Organization Admin');

    $this->actingAs($user)
        ->get(route('flagged-domains.index'))
        ->assertOk()
        ->assertDontSee('foreign-flagged.com');
});
