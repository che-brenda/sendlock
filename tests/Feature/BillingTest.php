<?php

use App\Models\Organization;
use App\Models\Payment;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Group',
        'type' => 'head',
        'status' => true,
        'subscription_plan' => 'Free',
        'subscription_status' => 'pending',
    ]);

    $this->admin = makeUser($this->org, 'Organization Admin');
});

test('a pending organization is held at the billing page', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertRedirect(route('billing.index'));

    // The billing page itself is reachable.
    $this->actingAs($this->admin)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('Professional')
        ->assertSee('Enterprise');
});

test('paying by card activates the subscription and unlocks the dashboard', function () {
    $this->actingAs($this->admin)
        ->post(route('billing.process', 'professional'), [
            'payment_method' => 'visa',
            'card_name' => 'Jane Doe',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ])
        ->assertRedirect(route('dashboard'));

    $this->org->refresh();

    expect($this->org->subscription_plan)->toBe('pro')
        ->and($this->org->subscription_status)->toBe('active')
        ->and($this->org->subscribed_at)->not->toBeNull()
        // Monthly billing sets a renewal ~1 month out.
        ->and($this->org->subscription_expires_at?->isFuture())->toBeTrue();

    $payment = Payment::where('organization_id', $this->org->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->payment_method)->toBe('visa')
        ->and((float) $payment->amount)->toBe(99.0)
        ->and($payment->plan)->toBe('pro');

    // Gate lifted.
    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
});

test('paying by MTN mobile money activates the subscription', function () {
    $this->actingAs($this->admin)
        ->post(route('billing.process', 'starter'), [
            'payment_method' => 'mtn_momo',
            'momo_name' => 'Jane Doe',
            'momo_phone' => '+233 24 123 4567',
        ])
        ->assertRedirect(route('dashboard'));

    $this->org->refresh();
    expect($this->org->subscription_plan)->toBe('beta')
        ->and($this->org->subscription_status)->toBe('active');
});

test('paying by PayPal activates the subscription', function () {
    $this->actingAs($this->admin)
        ->post(route('billing.process', 'enterprise'), [
            'payment_method' => 'paypal',
            'paypal_email' => 'payer@example.com',
        ])
        ->assertRedirect(route('dashboard'));

    $this->org->refresh();
    expect($this->org->subscription_plan)->toBe('enterprise')
        ->and($this->org->subscription_status)->toBe('active');
});

test('card payment is rejected when card fields are missing', function () {
    $this->actingAs($this->admin)
        ->post(route('billing.process', 'professional'), [
            'payment_method' => 'visa',
        ])
        ->assertSessionHasErrors(['card_name', 'card_number', 'card_expiry', 'card_cvv']);

    $this->org->refresh();
    expect($this->org->subscription_status)->toBe('pending');
    expect(Payment::count())->toBe(0);
});

test('choosing the free plan activates without payment and records no charge', function () {
    $this->actingAs($this->admin)
        ->post(route('billing.free'))
        ->assertRedirect(route('dashboard'));

    $this->org->refresh();

    expect($this->org->subscription_plan)->toBe('free')
        ->and($this->org->subscription_status)->toBe('active')
        ->and($this->org->subscribed_at)->not->toBeNull();

    expect(Payment::count())->toBe(0);

    // Gate lifted.
    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
});

test('the free package has no paid checkout page', function () {
    $this->actingAs($this->admin)
        ->get(route('billing.checkout', 'free'))
        ->assertRedirect(route('billing.index'));
});

test('the billing page lists the free option', function () {
    $this->actingAs($this->admin)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Free')
        ->assertSee('Continue free');
});

test('an unknown package 404s', function () {
    $this->actingAs($this->admin)
        ->get(route('billing.checkout', 'nope'))
        ->assertNotFound();
});

test('an org admin sees their subscription detail page after paying', function () {
    $this->org->update([
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
        'subscribed_at' => now(),
        'subscription_expires_at' => now()->addMonth(),
    ]);

    Payment::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'reference' => 'SL-TESTREF01',
        'package' => 'professional',
        'plan' => 'pro',
        'amount' => 99,
        'currency' => 'USD',
        'payment_method' => 'visa',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('Professional')
        ->assertSee('Renews / expires')
        ->assertSee('SL-TESTREF01');
});

test('the product owner sees every customer billing status', function () {
    $this->org->update([
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
        'subscribed_at' => now(),
    ]);
    Payment::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'reference' => 'SL-REV1',
        'package' => 'professional',
        'plan' => 'pro',
        'amount' => 99,
        'currency' => 'USD',
        'payment_method' => 'visa',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $super = makeUser($this->org, 'Super Admin');

    $this->actingAs($super)
        ->get(route('billing.customers'))
        ->assertOk()
        ->assertSee('Customer Billing')
        ->assertSee('Acme Group')
        ->assertSee('99.00');
});

test('a non-super-admin cannot reach the customer billing overview', function () {
    $this->org->update(['subscription_status' => 'active']);

    $this->actingAs($this->admin)
        ->get(route('billing.customers'))
        ->assertForbidden();
});

test('subscription state is expiry-aware', function () {
    $this->org->update([
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);
    expect($this->org->fresh()->subscriptionState())->toBe('active');

    // Within the "expiring soon" window.
    $this->org->update(['subscription_expires_at' => now()->addDays(3)]);
    expect($this->org->fresh()->subscriptionState())->toBe('expiring_soon');

    // Past the renewal date — expired even though the status column says active.
    $this->org->update(['subscription_expires_at' => now()->subDay()]);
    expect($this->org->fresh())
        ->subscriptionState()->toBe('expired')
        ->isSubscriptionExpired()->toBeTrue();

    // Free never expires.
    $this->org->update(['subscription_plan' => 'free', 'subscription_expires_at' => null]);
    expect($this->org->fresh()->subscriptionState())->toBe('free');
});

test('an org with an expired subscription sees a renewal prompt', function () {
    $this->org->update([
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
        'subscribed_at' => now()->subMonths(2),
        'subscription_expires_at' => now()->subDays(2),
    ]);

    $this->actingAs($this->admin)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('Renew now')
        ->assertSee('Expired');
});

test('the dashboard shows an org admin their subscription status', function () {
    $this->org->update([
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
        'subscribed_at' => now(),
        'subscription_expires_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Professional plan')
        ->assertSee('View subscription');
});

test('an already-subscribed organization is not gated', function () {
    $active = Organization::create([
        'organization_name' => 'Globex',
        'type' => 'head',
        'status' => true,
        'subscription_plan' => 'pro',
        'subscription_status' => 'active',
    ]);
    $user = makeUser($active, 'Organization Admin');

    $this->actingAs($user)->get('/dashboard')->assertOk();
});
