<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every request the firewall blocks — the evidence trail shown on the
        // Security Center ("threats blocked") and available for investigation.
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('rule');              // which signature tripped
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable();       // best-effort (may be pre-auth)
            $table->foreignId('organization_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
