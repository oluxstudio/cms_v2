<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sale of a paid template via Stripe Connect (destination charge). The platform
 * fee + creator amount are snapshotted; status moves pending → paid → refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // buyer
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('usd');
            $table->unsignedInteger('platform_fee_cents')->default(0);
            $table->unsignedInteger('creator_amount_cents')->default(0);
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('status')->default('pending'); // pending | paid | refunded
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_purchases');
    }
};
