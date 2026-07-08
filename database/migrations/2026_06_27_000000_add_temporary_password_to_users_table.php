<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when an admin creates a user: the account must replace its
            // system-issued temporary password on first sign-in before it can
            // use the rest of the app.
            $table->boolean('must_change_password')->default(false)->after('status');

            // The plaintext temporary password (stored encrypted at rest via the
            // model's `encrypted` cast) so the creating admin can read it off
            // their dashboard and hand it to the new user. Cleared the moment the
            // user sets their own password, so it only ever exists while unused.
            $table->text('temporary_password')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'temporary_password']);
        });
    }
};
