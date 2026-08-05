<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('started_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            // Per-CLIENT price overrides set by the platform admin: {plan: cents}.
            $table->json('price_overrides')->nullable()->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_subscription_id', 'price_overrides']);
        });
    }
};
