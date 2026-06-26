<?php

use App\Models\Organization;
use App\Models\ApprovalRequest;
use App\Models\RecipientVerification;
use App\Services\ApprovalWorkflow;
use App\Services\Verification\VerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

function pendingVerificationRequest(Organization $org, int $userId): ApprovalRequest
{
    return (new ApprovalWorkflow())->createFromEvaluation(
        ['risk_score' => 75, 'risk_level' => 'HIGH', 'decision' => 'RECIPIENT_VERIFY'],
        ['recipient_email' => 'vendor@partner.com', 'subject' => 'Invoice', 'email_content' => 'Body'],
        $org->id,
        $userId
    );
}

test('sending a code creates a pending verification', function () {
    $user = makeUser($this->org, 'Security Officer');
    $req = pendingVerificationRequest($this->org, $user->id);

    $this->actingAs($user)
        ->post(route('recipient-verification.send', $req), ['channel' => 'sms', 'phone' => '+1555000'])
        ->assertRedirect();

    $this->assertDatabaseHas('recipient_verifications', [
        'approval_request_id' => $req->id,
        'channel' => 'sms',
        'status' => 'PENDING',
    ]);
});

test('verifying with the correct code advances the request', function () {
    $user = makeUser($this->org, 'Security Officer');
    $req = pendingVerificationRequest($this->org, $user->id);

    $verification = (new VerificationService())->issue($req, 'email');

    $this->actingAs($user)
        ->post(route('recipient-verification.verify', $req), ['code' => $verification->code])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL);
    expect($verification->fresh()->status)->toBe(RecipientVerification::STATUS_VERIFIED);
});

test('verifying with a wrong code fails and keeps the request pending', function () {
    $user = makeUser($this->org, 'Security Officer');
    $req = pendingVerificationRequest($this->org, $user->id);

    (new VerificationService())->issue($req, 'email');

    $this->actingAs($user)
        ->post(route('recipient-verification.verify', $req), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_PENDING_VERIFICATION);
});

test('a user cannot verify another organization request', function () {
    $owner = makeUser($this->org, 'Security Officer');
    $req = pendingVerificationRequest($this->org, $owner->id);

    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $outsider = makeUser($otherOrg, 'Security Officer');

    $this->actingAs($outsider)
        ->post(route('recipient-verification.send', $req), ['channel' => 'sms'])
        ->assertForbidden();
});
