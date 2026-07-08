<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-organization record of who we've communicated with, aggregated by
        // counterpart address. Powers the "Previous Communication" signal and the
        // trusted-domain history on the risk-analysis page (Communication
        // Relationship Analysis / Recipient Intelligence).
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('counterpart_email')->index();
            $table->string('counterpart_domain')->index();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'counterpart_email']);
        });

        // Structured signal breakdown rendered by the risk-analysis page.
        Schema::table('email_scans', function (Blueprint $table) {
            $table->json('analysis')->nullable()->after('findings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');

        Schema::table('email_scans', function (Blueprint $table) {
            $table->dropColumn('analysis');
        });
    }
};
