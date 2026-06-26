<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

test('an org admin can add a trusted domain scoped to their organization', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->post(route('trust-center.trusted-domains.store'), [
            'domain' => 'Vendor.com',
            'vendor_name' => 'Vendor Inc',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('trusted_domains', [
        'organization_id' => $this->org->id,
        'domain' => 'vendor.com', // normalized to lowercase
        'vendor_name' => 'Vendor Inc',
    ]);
});

test('a normal employee cannot access the trust center', function () {
    $employee = makeUser($this->org, 'Employee');

    $this->actingAs($employee)
        ->get(route('trust-center.index'))
        ->assertForbidden();
});

test('an admin cannot delete another organization trusted domain', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $foreign = TrustedDomain::create([
        'organization_id' => $otherOrg->id,
        'domain' => 'foreign.com',
        'active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('trust-center.trusted-domains.destroy', $foreign))
        ->assertForbidden();

    $this->assertDatabaseHas('trusted_domains', ['id' => $foreign->id]);
});
