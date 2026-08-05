<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings feature — bookable services and the appointments visitors book against
 * them. Availability (open days/hours, slot length, timezone) is stored per-site
 * via SiteAttributes, so no extra table is needed for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_min')->default(30); // appointment length
            $table->unsignedInteger('price_cents')->default(0);        // 0 = free
            $table->char('currency', 3)->default('usd');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent the exact same slot being booked twice for a service.
            $table->unique(['site_id', 'service_id', 'starts_at']);
            $table->index(['site_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('services');
    }
};
