
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_domains', function (Blueprint $table) {

            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('domain');

            $table->string('vendor_name')
                ->nullable();

            $table->boolean('active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'domain',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_domains');
    }
};
