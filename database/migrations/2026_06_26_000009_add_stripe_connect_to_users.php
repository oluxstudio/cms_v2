<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe Connect (Express) state for creators who sell paid templates: their
 * connected account id and whether Stripe has enabled charges/payouts for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('email');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_account_id', 'stripe_charges_enabled']);
        });
    }
};
