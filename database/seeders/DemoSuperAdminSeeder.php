<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Ensures a platform Super Admin always exists so the deployed system is
 * immediately usable (and the account survives rebuilds, unlike a manually
 * created one). Credentials come from env so nothing sensitive is hardcoded:
 *   SENDLOCK_SUPERADMIN_EMAIL     (default superadmin@sendlock.app)
 *   SENDLOCK_SUPERADMIN_PASSWORD  (default SuperAdmin!2026 — change after login)
 *
 * Idempotent: the password is only set when the account is first created, so a
 * password you change through the UI is never overwritten on the next deploy.
 * Runs after RolesAndPermissionsSeeder (the Super Admin role must already exist).
 */
class DemoSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // No Super Admin role yet → roles haven't been seeded; nothing to do.
        if (! Role::where('name', 'Super Admin')->where('guard_name', 'web')->exists()) {
            return;
        }

        $email = env('SENDLOCK_SUPERADMIN_EMAIL', 'superadmin@sendlock.app');
        $password = env('SENDLOCK_SUPERADMIN_PASSWORD', 'SuperAdmin!2026');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make($password),
                'organization_id' => null,      // Super Admin is platform-wide, no tenant.
                'status' => true,
                'must_change_password' => false,
            ]
        );

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }
    }
}
