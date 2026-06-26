<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-populated register of impersonation / untrusted domains the risk engine
 * detected (lookalike, typosquat, or simply not in the Trust Center). Distinct
 * from `blocked_domains`, which is the admin-curated blocklist — this table is
 * machine-written, so a repeat use of the same domain can be warned on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flagged_domains', function (Blueprint $table) {

            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('domain');

            // 'untrusted' | 'typosquat' | 'lookalike'
            $table->string('detection_type');

            $table->text('reason')
                ->nullable();

            // The trusted vendor domain this one resembles (lookalike only).
            $table->string('resembles')
                ->nullable();

            $table->unsignedInteger('times_seen')
                ->default(1);

            $table->timestamp('first_seen_at')
                ->nullable();

            $table->timestamp('last_seen_at')
                ->nullable();

            $table->foreignId('last_seen_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'organization_id',
                'domain',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flagged_domains');
    }
};
