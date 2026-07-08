<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    // Registration assigns the "Organization Admin" role, so roles must exist.
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->post('/register', [
        'organization_name' => 'Acme Corp',
        'industry' => 'Logistics',
        'country_code' => '+1',
        'phone' => '555 0100',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    // New orgs go to billing first; the dashboard is gated until they subscribe.
    $response->assertRedirect(route('billing.index'));

    // Registration is org-centric: no personal name is collected, so the
    // founding account is identified by the organization name.
    $user = User::where('email', 'test@example.com')->first();
    expect($user->name)->toBe('Acme Corp');
    expect($user->first_name)->toBeNull();
    expect($user->last_name)->toBeNull();
    expect($user->display_name)->toBe('Acme Corp');
    expect($user->initials)->toBe('AC');
    expect($user->worker_number)->toBeNull();
    // Dial code + local number are combined into the stored phone.
    expect($user->phone)->toBe('+1 555 0100');
    expect($user->organization->phone)->toBe('+1 555 0100');
    expect($user->hasRole('Organization Admin'))->toBeTrue();
    expect($user->organization->isHead())->toBeTrue();
    expect($user->organization->subscriptionPending())->toBeTrue();
});

test('registration requires accepting the terms and conditions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->post('/register', [
        'organization_name' => 'Acme Corp',
        'industry' => 'Logistics',
        'country_code' => '+1',
        'phone' => '555 0100',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        // terms intentionally omitted
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration rejects an unsupported country dial code', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->post('/register', [
        'organization_name' => 'Acme Corp',
        'industry' => 'Logistics',
        'country_code' => '+999',
        'phone' => '555 0100',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $response->assertSessionHasErrors('country_code');
    $this->assertGuest();
});

test('the terms and conditions page is publicly reachable', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Terms & Conditions');
});
