<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an organization-scoped worker number (e.g. EMP-0001) that is the
     * human-facing staff identifier, distinct from the auto-generated users.id.
     * Unique per organization, not globally.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('worker_number')
                ->nullable()
                ->after('organization_id');

            $table->unique(['organization_id', 'worker_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'worker_number']);
            $table->dropColumn('worker_number');
        });
    }
};
