<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verified recipients are counterparties an organization has confirmed the
     * identity of (phone / WhatsApp / email). Part of the Trust Center.
     */
    public function up(): void
    {
        Schema::create('verified_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_recipients');
    }
};
