<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain purchases pay on the Go-live page with the Stripe Payment Element:
 * a PaymentIntent (domain only / renewal) or a Subscription whose first
 * invoice also carries the domain (domain + hosting plan). Orders sit in
 * status `checkout` until that payment succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->index()->after('stripe_session_id');
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_intent_id', 'stripe_subscription_id']);
        });
    }
};
