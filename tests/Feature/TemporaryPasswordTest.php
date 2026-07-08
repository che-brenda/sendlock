<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Group',
        'type' => 'head',
        'status' => true,
    ]);

    $this->admin = makeUser($this->org, 'Organization Admin');
});

test('creating a user issues a temporary password and flags a forced reset', function () {
    $this->actingAs($this->admin)
        ->post('/users', [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'worker_number' => 'EMP-9001',
            'email' => 'new.hire@acme.test',
            'role' => 'Employee',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'new.hire@acme.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->temporary_password)->not->toBeNull()
        // The stored login password is the hash of the issued temporary password.
        ->and(Hash::check($user->temporary_password, $user->password))->toBeTrue();
});

test('an admin sees the temporary password on the user list only while it is unused', function () {
    $pending = User::factory()->create([
        'organization_id' => $this->org->id,
        'email' => 'pending@acme.test',
        'status' => true,
        'must_change_password' => true,
        'temporary_password' => 'Temp-Secret-123',
    ]);
    $pending->assignRole('Employee');

    $this->actingAs($this->admin)
        ->get('/users')
        ->assertOk()
        ->assertSee('Temp-Secret-123');

    // Once the user has set their own password the credential is gone.
    $pending->update(['must_change_password' => false, 'temporary_password' => null]);

    $this->actingAs($this->admin)
        ->get('/users')
        ->assertOk()
        ->assertDontSee('Temp-Secret-123');
});

test('a user on a temporary password is forced to the first-change screen', function () {
    $newUser = User::factory()->create([
        'organization_id' => $this->org->id,
        'status' => true,
        'must_change_password' => true,
        'temporary_password' => 'Temp-Secret-123',
    ]);
    $newUser->assignRole('Employee');

    // Any app page bounces them to the reset screen...
    $this->actingAs($newUser)
        ->get('/dashboard')
        ->assertRedirect(route('password.first-change'));

    // ...but the reset screen itself is reachable.
    $this->actingAs($newUser)
        ->get(route('password.first-change'))
        ->assertOk();
});

test('setting a new password clears the temporary credential and unlocks the app', function () {
    $newUser = User::factory()->create([
        'organization_id' => $this->org->id,
        'status' => true,
        'must_change_password' => true,
        'temporary_password' => 'Temp-Secret-123',
    ]);
    $newUser->assignRole('Employee');

    $this->actingAs($newUser)
        ->put(route('password.first-change.update'), [
            'password' => 'my-own-strong-password',
            'password_confirmation' => 'my-own-strong-password',
        ])
        ->assertRedirect(route('dashboard'));

    $newUser->refresh();

    expect($newUser->must_change_password)->toBeFalse()
        ->and($newUser->temporary_password)->toBeNull()
        ->and(Hash::check('my-own-strong-password', $newUser->password))->toBeTrue();

    // The gate is lifted — the app is now reachable.
    $this->actingAs($newUser)
        ->get('/dashboard')
        ->assertOk();
});

test('a user who has already set their password is not redirected', function () {
    $user = User::factory()->create([
        'organization_id' => $this->org->id,
        'status' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole('Employee');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
