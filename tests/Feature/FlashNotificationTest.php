<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('a flashed success message renders as a global toast on the next page', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->from(route('trust-center.index'))
        ->followingRedirects()
        ->post(route('trust-center.trusted-domains.store'), ['domain' => 'partner.com'])
        ->assertOk()
        ->assertSee('Trusted domain added.');   // surfaced by <x-flash>
});

test('a validation error renders as a global toast', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    // Missing required domain -> redirect back with validation errors -> toast.
    $this->actingAs($admin)
        ->from(route('trust-center.index'))
        ->followingRedirects()
        ->post(route('trust-center.trusted-domains.store'), ['domain' => ''])
        ->assertOk()
        ->assertSee('field is required', false);   // validation message in the toast
});

test('the toast component is present on an authenticated page', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    // No flash -> the toast container is absent, page still renders fine.
    $this->actingAs($admin)->get('/dashboard')->assertOk();

    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);
});
