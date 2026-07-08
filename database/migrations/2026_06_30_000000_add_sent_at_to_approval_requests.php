<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            // When the user pressed "Send" after the message was cleared/released.
            // `released_at` = cleared to send; `sent_at` = actually dispatched.
            $table->timestamp('sent_at')->nullable()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
