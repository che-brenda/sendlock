<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Decision Engine's verdict for a scan:
     * ALLOW | MANAGER_APPROVAL | RECIPIENT_VERIFY | QUARANTINE.
     */
    public function up(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->string('decision')
                  ->default('ALLOW')
                  ->after('risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->dropColumn('decision');
        });
    }
};
