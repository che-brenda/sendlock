<?php

use App\Models\AuditLog;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->head = Organization::create([
        'organization_name' => 'Acme Group',
        'type' => 'head',
        'status' => true,
    ]);

    $this->sub = Organization::create([
        'organization_name' => 'Acme Europe',
        'type' => 'sub',
        'parent_id' => $this->head->id,
        'status' => true,
    ]);

    $this->other = Organization::create([
        'organization_name' => 'Globex',
        'type' => 'head',
        'status' => true,
    ]);
});

function auditLog(Organization $org, int $userId, string $description): AuditLog
{
    return AuditLog::create([
        'organization_id' => $org->id,
        'user_id' => $userId,
        'action' => 'CREATE',
        'entity_type' => 'USER',
        'entity_id' => $userId,
        'description' => $description,
    ]);
}

test('an employee only sees audit logs of their own actions', function () {
    $me = makeUser($this->head, 'Employee');
    $colleague = makeUser($this->head, 'Manager');

    auditLog($this->head, $me->id, 'My own action');
    auditLog($this->head, $colleague->id, 'Colleague action');

    $this->actingAs($me)
        ->get('/audit-logs')
        ->assertOk()
        ->assertSee('My own action')
        ->assertDontSee('Colleague action');
});

test('an organization admin sees the whole org tree but not other organizations', function () {
    $admin = makeUser($this->head, 'Organization Admin');
    $employee = makeUser($this->head, 'Employee');
    $subEmployee = makeUser($this->sub, 'Employee');
    $stranger = makeUser($this->other, 'Employee');

    auditLog($this->head, $employee->id, 'Head org action');
    auditLog($this->sub, $subEmployee->id, 'Sub org action');
    auditLog($this->other, $stranger->id, 'Foreign org action');

    $this->actingAs($admin)
        ->get('/audit-logs')
        ->assertOk()
        ->assertSee('Head org action')
        ->assertSee('Sub org action')
        ->assertDontSee('Foreign org action');
});

test('a super admin sees logs across every organization', function () {
    $super = makeUser($this->head, 'Super Admin');
    $employee = makeUser($this->head, 'Employee');
    $stranger = makeUser($this->other, 'Employee');

    auditLog($this->head, $employee->id, 'Home org action');
    auditLog($this->other, $stranger->id, 'Foreign org action');

    $this->actingAs($super)
        ->get('/audit-logs')
        ->assertOk()
        ->assertSee('Home org action')
        ->assertSee('Foreign org action');
});
