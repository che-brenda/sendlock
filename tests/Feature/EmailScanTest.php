<?php

use App\Models\BlockedDomain;
use App\Models\EmailScan;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

test('a user can analyze an email and the scan is persisted with a decision', function () {
    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->post(route('email-scans.analyze'), [
            'sender_email' => 'someone@unknown-vendor.com',
            'subject' => 'Urgent payment',
            'email_content' => 'Please process this wire transfer urgently.',
        ])
        ->assertRedirect();

    $scan = EmailScan::where('organization_id', $this->org->id)->first();

    expect($scan)->not->toBeNull();
    expect($scan->sender_domain)->toBe('unknown-vendor.com');
    expect($scan->decision)->toBeIn(['ALLOW', 'MANAGER_APPROVAL', 'RECIPIENT_VERIFY', 'QUARANTINE']);
    expect($scan->user_id)->toBe($user->id);
});

test('a blocked domain scan is recorded as quarantined', function () {
    BlockedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'fraud.com',
        'active' => true,
    ]);

    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->post(route('email-scans.analyze'), ['sender_email' => 'x@fraud.com'])
        ->assertRedirect();

    $scan = EmailScan::where('organization_id', $this->org->id)->first();

    expect($scan->risk_level)->toBe('CRITICAL');
    expect($scan->decision)->toBe('QUARANTINE');
    expect($scan->is_blocked_domain)->toBeTrue();
});

test('the scan list only shows the current organization scans', function () {
    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);

    EmailScan::create([
        'organization_id' => $otherOrg->id,
        'user_id' => makeUser($otherOrg, 'Employee')->id,
        'sender_email' => 'foreign@other.com',
        'sender_domain' => 'other.com',
        'risk_score' => 10,
        'risk_level' => 'LOW',
        'decision' => 'ALLOW',
    ]);

    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->get(route('email-scans.index'))
        ->assertOk()
        ->assertDontSee('foreign@other.com');
});
