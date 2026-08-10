<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();

            // Privacy: a daily-salted hash of the IP (never the raw IP), so we
            // can count unique visitors per day without retaining PII.
            $table->string('visitor_hash', 64)->index();
            $table->string('session_id', 64)->nullable();

            // Where they landed and where they came from.
            $table->string('path', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('source', 20)->default('direct'); // direct|referral|organic|social|email|campaign
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            // Geolocation (from IP; city-level at best, may be null).
            $table->char('country_code', 2)->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            // Device / client (from the user agent).
            $table->string('device_type', 20)->nullable(); // desktop|smartphone|tablet|tv|console|...
            $table->string('os')->nullable();
            $table->string('os_version')->nullable();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('language', 12)->nullable();
            $table->boolean('is_bot')->default(false);

            $table->timestamp('created_at')->nullable();

            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'country_code']);
            $table->index(['site_id', 'device_type']);
            $table->index(['site_id', 'referrer_host']);
            $table->index(['site_id', 'source']);
            $table->index(['site_id', 'is_bot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
