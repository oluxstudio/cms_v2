<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            // Secrets are stored via Laravel 'encrypted' casts (long ciphertext).
            $table->text('stripe_secret')->nullable();
            $table->string('stripe_publishable')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->boolean('livemode')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_payment_settings');
    }
};
