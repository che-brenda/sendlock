<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An outbound "protected send" request that moves through the verification
     * and approval workflow before the email is released.
     */
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_scan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('recipient_email');
            $table->string('subject')->nullable();
            $table->longText('email_content')->nullable();

            $table->integer('risk_score')->default(0);
            $table->string('risk_level')->default('LOW');
            $table->string('decision')->default('ALLOW');

            // PENDING_VERIFICATION | PENDING_APPROVAL | RELEASED | REJECTED | BLOCKED
            $table->string('status')->default('RELEASED');

            $table->boolean('requires_verification')->default(false);
            $table->boolean('requires_approval')->default(false);

            $table->timestamp('recipient_verified_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
