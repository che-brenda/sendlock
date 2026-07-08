<?php

use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;

test('it blocks path traversal in the query string', function () {
    $this->get('/?file=../../etc/passwd')->assertForbidden();

    $this->assertDatabaseHas('security_events', ['method' => 'GET']);
});

test('it blocks reflected XSS in the query string', function () {
    $this->get('/?q=<script>alert(1)</script>')->assertForbidden();
});

test('it blocks SQL injection in the query string', function () {
    $this->get('/?id=1 union select password from users')->assertForbidden();
});

test('it blocks known scanner user-agents', function () {
    $this->get('/', ['User-Agent' => 'sqlmap/1.7'])->assertForbidden();

    $this->assertDatabaseHas('security_events', ['rule' => 'blocked_agent']);
});

test('a clean request passes and carries the security headers', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('malicious-looking email content in the BODY is NOT firewalled', function () {
    // The app exists to analyse this exact content — the WAF must not block it.
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
    $user = makeUser($org, 'Employee');

    $this->actingAs($user)
        ->post(route('email-scans.analyze'), [
            'sender_email' => 'attacker@evil.test',
            'subject' => 'union select * from users',
            'email_content' => '<script>alert(document.cookie)</script> please run this ../../etc/passwd',
        ])
        ->assertRedirect();   // scanned normally, not a 403
});

test('the firewall can be disabled by config', function () {
    config(['firewall.enabled' => false]);

    $this->get('/?q=<script>alert(1)</script>')->assertOk();
});
