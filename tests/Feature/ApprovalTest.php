<?php

use App\Models\Organization;
use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

function pendingApprovalRequest(Organization $org, int $userId): ApprovalRequest
{
    return (new ApprovalWorkflow())->createFromEvaluation(
        ['risk_score' => 50, 'risk_level' => 'MEDIUM', 'decision' => 'MANAGER_APPROVAL'],
        ['recipient_email' => 'vendor@partner.com', 'subject' => 'Invoice', 'email_content' => 'Body'],
        $org->id,
        $userId
    );
}

test('a manager can approve a pending request which releases it', function () {
    $employee = makeUser($this->org, 'Employee');
    $manager = makeUser($this->org, 'Manager');
    $req = pendingApprovalRequest($this->org, $employee->id);

    $this->actingAs($manager)
        ->post(route('approvals.approve', $req), ['notes' => 'ok'])
        ->assertRedirect();

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_RELEASED);
});

test('a manager can reject a pending request', function () {
    $employee = makeUser($this->org, 'Employee');
    $manager = makeUser($this->org, 'Manager');
    $req = pendingApprovalRequest($this->org, $employee->id);

    $this->actingAs($manager)
        ->post(route('approvals.reject', $req), ['notes' => 'no'])
        ->assertRedirect();

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_REJECTED);
});

test('an employee cannot access the approvals queue', function () {
    $employee = makeUser($this->org, 'Employee');

    $this->actingAs($employee)
        ->get(route('approvals.index'))
        ->assertForbidden();
});

test('a manager cannot approve another organization request', function () {
    $employee = makeUser($this->org, 'Employee');
    $req = pendingApprovalRequest($this->org, $employee->id);

    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $foreignManager = makeUser($otherOrg, 'Manager');

    $this->actingAs($foreignManager)
        ->post(route('approvals.approve', $req))
        ->assertForbidden();

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_PENDING_APPROVAL);
});
