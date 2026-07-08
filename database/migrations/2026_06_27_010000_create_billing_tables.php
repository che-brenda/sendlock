<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Subscription lifecycle for the billing gate. `pending` = registered
            // but not yet paid (held at the billing page); `active` = a package
            // has been paid for. Null on pre-existing orgs, which are treated as
            // not gated. `subscribed_at` stamps the most recent successful payment.
            $table->string('subscription_status')->nullable()->after('subscription_plan');
            $table->timestamp('subscribed_at')->nullable()->after('subscription_status');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();      // human-facing receipt id
            $table->string('package');                  // config billing package key
            $table->string('plan');                     // entitlement plan granted
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('billing_cycle')->default('monthly');
            $table->string('payment_method');           // visa | mtn_momo | paypal
            $table->string('status')->default('paid');  // stubbed: always paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'subscribed_at']);
        });
    }
};
