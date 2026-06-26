<?php

use App\Models\Department;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

test('an admin can create a department in their organization', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->post(route('departments.store'), ['department_name' => 'Finance', 'description' => 'Money team'])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', [
        'organization_id' => $this->org->id,
        'department_name' => 'Finance',
    ]);
});

test('a department from another organization is not accessible', function () {
    $admin = makeUser($this->org, 'Organization Admin');

    $otherOrg = Organization::create(['organization_name' => 'Other', 'type' => 'head', 'status' => true]);
    $foreign = Department::create(['organization_id' => $otherOrg->id, 'department_name' => 'Foreign', 'status' => true]);

    $this->actingAs($admin)->get(route('departments.show', $foreign))->assertNotFound();
    $this->actingAs($admin)->put(route('departments.update', $foreign), ['department_name' => 'Hacked'])->assertNotFound();
    $this->actingAs($admin)->delete(route('departments.destroy', $foreign))->assertNotFound();

    $this->assertDatabaseHas('departments', ['id' => $foreign->id, 'department_name' => 'Foreign']);
});
