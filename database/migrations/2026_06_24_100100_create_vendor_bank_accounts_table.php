<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Known-good banking details per vendor domain. The Financial Data
     * Comparison Engine matches account numbers / IBANs found in email content
     * against these records to detect bank-change fraud.
     */
    public function up(): void
    {
        Schema::create('vendor_bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('vendor_name')->nullable();
            $table->string('vendor_domain');
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift')->nullable();
            $table->string('label')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'vendor_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bank_accounts');
    }
};
