<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalized cache of external threat-feed lookups (keyed by domain) so
     * repeat scans don't re-hit rate-limited free-tier APIs.
     */
    public function up(): void
    {
        Schema::create('threat_intel_cache', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->boolean('is_threat')->default(false);
            $table->string('severity')->nullable();
            $table->string('category')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_intel_cache');
    }
};
