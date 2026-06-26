<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ApprovalRequest;
use App\Models\VerifiedRecipient;
use App\Services\ApprovalWorkflow;

beforeEach(function () {
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'status' => true]);
    $this->workflow = new ApprovalWorkflow();
});

function evaluation(string $decision, int $score = 50, string $level = 'MEDIUM'): array
{
    return ['risk_score' => $score, 'risk_level' => $level, 'decision' => $decision];
}

function emailTo(string $recipient = 'vendor@partner.com'): array
{
    return ['recipient_email' => $recipient, 'subject' => 'Invoice', 'email_content' => 'Body'];
}

test('an allowed decision releases immediately', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('ALLOW', 10, 'LOW'), emailTo(), $this->org->id, $this->user->id);

    expect($req->status)->toBe(ApprovalRequest::STATUS_RELEASED);
    expect($req->released_at)->not->toBeNull();
});

test('a manager-approval decision waits for approval', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('MANAGER_APPROVAL'), emailTo(), $this->org->id, $this->user->id);

    expect($req->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL);
    expect($req->requires_approval)->toBeTrue();
    expect($req->requires_verification)->toBeFalse();
});

test('a recipient-verify decision requires verification then approval', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('RECIPIENT_VERIFY', 75, 'HIGH'), emailTo(), $this->org->id, $this->user->id);

    expect($req->status)->toBe(ApprovalRequest::STATUS_PENDING_VERIFICATION);
    expect($req->requires_verification)->toBeTrue();
    expect($req->requires_approval)->toBeTrue();
});

test('a pre-verified recipient skips the verification step', function () {
    VerifiedRecipient::create([
        'organization_id' => $this->org->id,
        'email' => 'trusted@partner.com',
        'verified' => true,
    ]);

    $req = $this->workflow->createFromEvaluation(
        evaluation('RECIPIENT_VERIFY', 75, 'HIGH'),
        emailTo('trusted@partner.com'),
        $this->org->id,
        $this->user->id
    );

    expect($req->requires_verification)->toBeFalse();
    expect($req->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL);
});

test('a quarantine decision is blocked', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('QUARANTINE', 100, 'CRITICAL'), emailTo(), $this->org->id, $this->user->id);

    expect($req->status)->toBe(ApprovalRequest::STATUS_BLOCKED);
});

test('the full high-risk flow advances verify then approve to released', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('RECIPIENT_VERIFY', 75, 'HIGH'), emailTo(), $this->org->id, $this->user->id);

    $this->workflow->markRecipientVerified($req);
    expect($req->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL);
    expect($req->recipient_verified_at)->not->toBeNull();

    $this->workflow->approve($req, $this->user->id, 'looks good');
    expect($req->status)->toBe(ApprovalRequest::STATUS_RELEASED);
    expect($req->released_at)->not->toBeNull();
    $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $req->id, 'action' => 'APPROVED']);
});

test('rejecting a request marks it rejected', function () {
    $req = $this->workflow->createFromEvaluation(evaluation('MANAGER_APPROVAL'), emailTo(), $this->org->id, $this->user->id);

    $this->workflow->reject($req, $this->user->id, 'no');

    expect($req->status)->toBe(ApprovalRequest::STATUS_REJECTED);
    $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $req->id, 'action' => 'REJECTED']);
});
