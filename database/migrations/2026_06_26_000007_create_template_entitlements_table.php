<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who may install a template. Free templates grant an entitlement on "Get"; paid
 * ones on a completed purchase. Install is gated on the presence of a row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('free');        // free | purchase | granted
            $table->foreignId('purchase_id')->nullable();      // → template_purchases (paid)
            $table->timestamps();

            $table->unique(['user_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_entitlements');
    }
};
