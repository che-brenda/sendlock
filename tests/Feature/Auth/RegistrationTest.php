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
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    // Registration creates the org and its first admin. Worker number is
    // assigned manually later, so it starts empty for the founder.
    $user = User::where('email', 'test@example.com')->first();
    expect($user->worker_number)->toBeNull();
    expect($user->hasRole('Organization Admin'))->toBeTrue();
    expect($user->organization->isHead())->toBeTrue();
});
