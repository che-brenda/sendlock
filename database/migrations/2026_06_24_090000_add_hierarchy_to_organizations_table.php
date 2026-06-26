<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the organization hierarchy: a head organization (parent_id = null,
     * type = 'head') may own many sub-organizations (parent_id set, type = 'sub').
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('parent_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('organizations')
                  ->nullOnDelete();

            $table->string('type')
                  ->default('head')
                  ->after('organization_name'); // head | sub
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('type');
        });
    }
};
