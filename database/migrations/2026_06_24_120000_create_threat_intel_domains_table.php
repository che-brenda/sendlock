<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide threat intelligence: known-malicious / suspicious domains
     * curated by Super Admins and shared across all tenants. Not org-scoped.
     */
    public function up(): void
    {
        Schema::create('threat_intel_domains', function (Blueprint $table) {
            $table->id();

            $table->string('domain')->unique();
            $table->string('category')->nullable(); // phishing | malware | bec | spam
            $table->string('severity')->default('MEDIUM'); // LOW | MEDIUM | HIGH
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_intel_domains');
    }
};
