<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Risk Engine now emits a verdict confidence (0–100) and a list of
     * operator recommendations alongside the score/level/decision. Persist both
     * wherever a verdict is stored.
     */
    public function up(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->after('decision');
            $table->json('recommendations')->nullable()->after('confidence');
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->after('decision');
            $table->json('recommendations')->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->dropColumn(['confidence', 'recommendations']);
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn(['confidence', 'recommendations']);
        });
    }
};
