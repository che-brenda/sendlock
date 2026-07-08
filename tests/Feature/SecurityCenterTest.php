<?php

use App\Models\Organization;
use App\Models\SecurityEvent;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('the security center shows the posture and blocked-attack tally', function () {
    SecurityEvent::create(['rule' => 'xss', 'ip_address' => '1.2.3.4', 'method' => 'GET', 'path' => '/?q=x']);

    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)
        ->get(route('security.index'))
        ->assertOk()
        ->assertSee('Security Center')
        ->assertSee('Application firewall')
        ->assertSee('Tenant isolation')
        ->assertSee('Firewall Active');
});

test('a super admin sees recent blocked requests, an employee does not', function () {
    SecurityEvent::create(['rule' => 'sql_injection', 'ip_address' => '9.9.9.9', 'method' => 'GET', 'path' => '/?id=1']);

    $super = makeUser($this->org, 'Super Admin');
    $this->actingAs($super)
        ->get(route('security.index'))
        ->assertOk()
        ->assertSee('Recent blocked requests')
        ->assertSee('9.9.9.9');

    $employee = makeUser($this->org, 'Employee');
    $this->actingAs($employee)
        ->get(route('security.index'))
        ->assertOk()
        ->assertDontSee('Recent blocked requests');
});
