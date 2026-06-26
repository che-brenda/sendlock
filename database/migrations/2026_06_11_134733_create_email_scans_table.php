
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_scans', function (Blueprint $table) {

            $table->id();

            $table->foreignId('organization_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('sender_email');

            $table->string('sender_domain');

            $table->string('subject')
                  ->nullable();

            $table->longText('email_content')
                  ->nullable();

            $table->integer('risk_score')
                  ->default(0);

            $table->string('risk_level')
                  ->default('LOW');

            $table->json('findings')
                  ->nullable();

            $table->boolean('is_trusted_domain')
                  ->default(false);

            $table->boolean('is_blocked_domain')
                  ->default(false);

            $table->boolean('spf_pass')
                  ->default(false);

            $table->boolean('dkim_pass')
                  ->default(false);

            $table->boolean('dmarc_pass')
                  ->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_scans');
    }
};

