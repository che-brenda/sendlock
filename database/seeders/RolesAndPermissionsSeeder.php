<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            'manage users',
            'manage organizations',
            'manage vendors',
            'manage domains',
            'manage policies',
            'approve emails',
            'view reports',
            'view audit logs',
            'view dashboard',
            'send emails',
            'manage approvals'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Create Roles
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        $headOrganizationAdmin = Role::firstOrCreate([
            'name' => 'Head Organization Admin',
            'guard_name' => 'web'
        ]);

        $organizationAdmin = Role::firstOrCreate([
            'name' => 'Organization Admin',
            'guard_name' => 'web'
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'Employee',
            'guard_name' => 'web'
        ]);

        $manager = Role::firstOrCreate([
            'name' => 'Manager',
            'guard_name' => 'web'
        ]);

        $securityOfficer = Role::firstOrCreate([
            'name' => 'Security Officer',
            'guard_name' => 'web'
        ]);

        $auditor = Role::firstOrCreate([
            'name' => 'Auditor',
            'guard_name' => 'web'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assign Permissions to Roles
        |--------------------------------------------------------------------------
        */

        // Super Admin gets everything
        $superAdmin->syncPermissions(Permission::all());

        // Head Organization Admin: manages its own org and all sub-organizations
        $headOrganizationAdmin->syncPermissions([
            'manage organizations',
            'manage users',
            'manage vendors',
            'manage domains',
            'manage policies',
            'view reports',
            'view audit logs',
            'view dashboard',
            'manage approvals'
        ]);

        // Organization Admin
        $organizationAdmin->syncPermissions([
            'manage users',
            'manage vendors',
            'manage domains',
            'view reports',
            'view dashboard',
            'manage approvals'
        ]);

        // Employee
        $employee->syncPermissions([
            'send emails',
            'view dashboard'
        ]);

        // Manager
        $manager->syncPermissions([
            'approve emails',
            'view reports',
            'view dashboard',
            'manage approvals'
        ]);

        // Security Officer
        $securityOfficer->syncPermissions([
            'view audit logs',
            'manage policies',
            'view reports',
            'view dashboard'
        ]);

        // Auditor
        $auditor->syncPermissions([
            'view reports',
            'view audit logs',
            'view dashboard'
        ]);
    }
}