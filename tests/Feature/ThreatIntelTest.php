<?php

use App\Models\Organization;
use App\Models\ThreatIntelDomain;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('a super admin can add a threat domain (normalized)', function () {
    $super = makeUser($this->org, 'Super Admin');

    $this->actingAs($super)
        ->post(route('threat-intel.store'), [
            'domain' => 'HTTPS://Evil.com/path',
            'category' => 'phishing',
            'severity' => 'HIGH',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('threat_intel_domains', ['domain' => 'evil.com', 'severity' => 'HIGH']);
});

test('a non super admin cannot access global threat intel', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->get(route('threat-intel.index'))
        ->assertForbidden();
});

test('a super admin can remove a threat domain', function () {
    $super = makeUser($this->org, 'Super Admin');
    $domain = ThreatIntelDomain::create(['domain' => 'evil.com', 'severity' => 'HIGH']);

    $this->actingAs($super)
        ->delete(route('threat-intel.destroy', $domain))
        ->assertRedirect();

    $this->assertDatabaseMissing('threat_intel_domains', ['id' => $domain->id]);
});
