<?php

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Models\VerifiedRecipient;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
    $this->user = makeUser($this->org, 'Employee');
});

/** A released request that cleared BOTH verification and approval. */
function verifiedApprovedRequest(Organization $org, int $userId, string $recipient): ApprovalRequest
{
    $req = ApprovalRequest::create([
        'organization_id' => $org->id,
        'user_id' => $userId,
        'recipient_email' => $recipient,
        'risk_score' => 75,
        'risk_level' => 'HIGH',
        'decision' => 'RECIPIENT_VERIFY',
        'status' => ApprovalRequest::STATUS_RELEASED,
        'recipient_verified_at' => now(),
        'released_at' => now(),
    ]);

    ApprovalAction::create([
        'approval_request_id' => $req->id,
        'organization_id' => $org->id,
        'user_id' => $userId,
        'action' => ApprovalAction::ACTION_APPROVED,
    ]);

    return $req;
}

test('a send to an untrusted address is never auto-released', function () {
    $response = $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'stranger@unknown-co.com',
        'subject' => 'Hi',
        'email_content' => 'Please see attached.',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    expect($req->status)->not->toBe(ApprovalRequest::STATUS_RELEASED)
        ->and($req->requires_approval)->toBeTrue()
        ->and($req->released_at)->toBeNull();

    $response->assertRedirect(route('protected-email.show', $req));
});

test('a send to a trusted domain is released and shows the safe-to-send action', function () {
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'vendor@partner.com',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    expect($req->status)->toBe(ApprovalRequest::STATUS_RELEASED)
        ->and($req->sent_at)->toBeNull();   // cleared, but NOT auto-sent

    // The user sees the safe-to-send confirmation with a Send button.
    $this->actingAs($this->user)
        ->get(route('protected-email.show', $req))
        ->assertOk()
        ->assertSee('Safe to send')
        ->assertSee('Send email')
        ->assertSee('Recipient found in your trusted database');
});

test('a cleared message is only sent when the user presses Send', function () {
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $this->actingAs($this->user)->post(route('protected-email.store'), ['recipient_email' => 'vendor@partner.com']);
    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    $this->actingAs($this->user)
        ->post(route('protected-email.send', $req))
        ->assertRedirect();

    expect($req->fresh()->sent_at)->not->toBeNull();
});

test('a message that is not released cannot be sent', function () {
    $req = ApprovalRequest::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'recipient_email' => 'pending@vendor.com',
        'risk_score' => 75,
        'risk_level' => 'HIGH',
        'decision' => 'RECIPIENT_VERIFY',
        'status' => ApprovalRequest::STATUS_PENDING_VERIFICATION,
    ]);

    $this->actingAs($this->user)
        ->post(route('protected-email.send', $req))
        ->assertStatus(422);

    expect($req->fresh()->sent_at)->toBeNull();
});

test('a public-provider domain in the trust list does NOT trust its addresses', function () {
    // Even with gmail.com registered as a trusted domain, an unverified gmail
    // address must still be gated — the exact scenario the user reported.
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'gmail.com', 'active' => true]);

    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'nnnnnm@gmail.com',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    expect($req->status)->not->toBe(ApprovalRequest::STATUS_RELEASED)
        ->and($req->requires_approval)->toBeTrue();
});

test('a specific verified address at a public provider IS trusted', function () {
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'gmail.com', 'active' => true]);
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'chebrenda93@gmail.com', 'verified' => true, 'verified_at' => now()]);

    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'chebrenda93@gmail.com',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();
    expect($req->status)->toBe(ApprovalRequest::STATUS_RELEASED);
});

test('a public email provider cannot be added as a trusted domain', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->post(route('trust-center.trusted-domains.store'), ['domain' => 'gmail.com'])
        ->assertSessionHasErrors('domain');

    $this->assertDatabaseMissing('trusted_domains', ['organization_id' => $this->org->id, 'domain' => 'gmail.com']);
});

test('a send to a verified contact is released', function () {
    VerifiedRecipient::create(['organization_id' => $this->org->id, 'email' => 'known@somewhere.com', 'verified' => true, 'verified_at' => now()]);

    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'known@somewhere.com',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    expect($req->status)->toBe(ApprovalRequest::STATUS_RELEASED);
});

test('an untrusted send shows all the options and links a risk analysis', function () {
    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'stranger@unknown-co.com',
    ]);
    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    // A scan was persisted and linked for the "View risk analysis" option.
    expect($req->email_scan_id)->not->toBeNull();
    $this->assertDatabaseHas('email_scans', ['id' => $req->email_scan_id, 'organization_id' => $this->org->id]);

    $this->actingAs($this->user)
        ->get(route('protected-email.show', $req))
        ->assertOk()
        ->assertSee('Verify recipient (SMS / WhatsApp)')
        ->assertSee('Request manager authorization')
        ->assertSee('View risk analysis')
        ->assertSee('Cancel');
});

test('a sender can cancel an untrusted send', function () {
    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'stranger@unknown-co.com',
    ]);
    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    $this->actingAs($this->user)
        ->post(route('protected-email.cancel', $req))
        ->assertRedirect(route('protected-email.create'));

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_CANCELLED)
        ->and($req->fresh()->sent_at)->toBeNull();
});

test('a finalized request cannot be cancelled', function () {
    $req = ApprovalRequest::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'recipient_email' => 'done@vendor.com',
        'risk_score' => 5,
        'risk_level' => 'SAFE',
        'decision' => 'ALLOW',
        'status' => ApprovalRequest::STATUS_RELEASED,
    ]);

    $this->actingAs($this->user)
        ->post(route('protected-email.cancel', $req))
        ->assertStatus(422);
});

test('the flagged-domain popup shows the verify and risk-analysis options', function () {
    $this->actingAs($this->user)
        ->withSession(['domain_warning' => [
            'domain' => 'gmail.com',
            'type' => 'untrusted',
            'reason' => 'Public email provider',
            'times_seen' => 19,
            'resembles' => null,
            'context' => 'send',
            'email' => ['recipient_email' => 'nnnnnm@gmail.com', 'subject' => null, 'email_content' => null],
        ]])
        ->get(route('protected-email.create'))
        ->assertOk()
        ->assertSee('View risk analysis')
        ->assertSee('Verify recipient')
        ->assertSee('Send anyway');
});

test('the popup verify option creates the request and lands on verification', function () {
    $this->actingAs($this->user)
        ->post(route('protected-email.store'), [
            'recipient_email' => 'x@unknown-co.com',
            'acknowledged' => 1,
            'intent' => 'verify',
        ])
        ->assertRedirect(route('recipient-verification.index'));

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();
    expect($req)->not->toBeNull()->and($req->email_scan_id)->not->toBeNull();
});

test('the popup risk-analysis option lands on the scan analysis page', function () {
    $response = $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'x@unknown-co.com',
        'acknowledged' => 1,
        'intent' => 'analysis',
    ]);

    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    $response->assertRedirect(route('email-scans.show', $req->email_scan_id));
});

test('a sender can escalate an untrusted send to a manager', function () {
    $this->actingAs($this->user)->post(route('protected-email.store'), [
        'recipient_email' => 'stranger@unknown-co.com',
    ]);
    $req = ApprovalRequest::where('organization_id', $this->org->id)->latest()->first();

    $this->actingAs($this->user)
        ->post(route('protected-email.escalate', $req))
        ->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL)
        ->and($req->requires_verification)->toBeFalse();
});

test('after verification and approval, the address can be added to verified contacts on confirmation', function () {
    $req = verifiedApprovedRequest($this->org, $this->user->id, 'cleared@vendor.com');

    $this->actingAs($this->user)
        ->post(route('protected-email.trust', $req), ['scope' => 'address'])
        ->assertRedirect();

    $this->assertDatabaseHas('verified_recipients', [
        'organization_id' => $this->org->id,
        'email' => 'cleared@vendor.com',
        'verified' => true,
    ]);
});

test('after verification and approval, the whole domain can be trusted on confirmation', function () {
    $req = verifiedApprovedRequest($this->org, $this->user->id, 'cleared@vendor.com');

    $this->actingAs($this->user)
        ->post(route('protected-email.trust', $req), ['scope' => 'domain'])
        ->assertRedirect();

    $this->assertDatabaseHas('trusted_domains', [
        'organization_id' => $this->org->id,
        'domain' => 'vendor.com',
        'active' => true,
    ]);
});

test('a recipient that was approved but NOT verified cannot be trusted', function () {
    // Released via escalation (manager approved, recipient never verified).
    $req = ApprovalRequest::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'recipient_email' => 'escalated@vendor.com',
        'risk_score' => 75,
        'risk_level' => 'HIGH',
        'decision' => 'RECIPIENT_VERIFY',
        'status' => ApprovalRequest::STATUS_RELEASED,
        'recipient_verified_at' => null,
    ]);
    ApprovalAction::create([
        'approval_request_id' => $req->id,
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'action' => ApprovalAction::ACTION_APPROVED,
    ]);

    $this->actingAs($this->user)
        ->post(route('protected-email.trust', $req), ['scope' => 'address'])
        ->assertStatus(422);

    $this->assertDatabaseMissing('verified_recipients', ['email' => 'escalated@vendor.com']);
});

test('a not-yet-released recipient cannot be trusted', function () {
    $req = ApprovalRequest::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'recipient_email' => 'pending@vendor.com',
        'risk_score' => 75,
        'risk_level' => 'HIGH',
        'decision' => 'RECIPIENT_VERIFY',
        'status' => ApprovalRequest::STATUS_PENDING_VERIFICATION,
    ]);

    $this->actingAs($this->user)
        ->post(route('protected-email.trust', $req), ['scope' => 'address'])
        ->assertStatus(422);

    $this->assertDatabaseMissing('verified_recipients', ['email' => 'pending@vendor.com']);
});

test('the trust action is tenant scoped', function () {
    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $req = verifiedApprovedRequest($otherOrg, makeUser($otherOrg, 'Employee')->id, 'x@foreign.com');

    $this->actingAs($this->user)
        ->post(route('protected-email.trust', $req), ['scope' => 'address'])
        ->assertForbidden();
});
