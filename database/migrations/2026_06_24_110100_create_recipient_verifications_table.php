<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A one-time verification challenge issued to a recipient over a channel
     * (SMS / WhatsApp / email) before a high-risk email may proceed.
     */
    public function up(): void
    {
        Schema::create('recipient_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('recipient_email');
            $table->string('recipient_phone')->nullable();
            $table->string('channel'); // sms | whatsapp | email
            $table->string('code');

            $table->string('status')->default('PENDING'); // PENDING | VERIFIED | EXPIRED
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_verifications');
    }
};
