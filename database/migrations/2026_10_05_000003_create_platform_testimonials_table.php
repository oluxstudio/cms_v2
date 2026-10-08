<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testimonials about OLUX itself (the landing page carousel) — written by
 * customers on the public form or added by super admins. Public submissions
 * arrive `pending` and only show once an admin publishes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_testimonials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('role', 120)->nullable()->comment('Business / job title shown under the name');
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable()->comment('1–5 stars');
            $table->string('email', 160)->nullable()->comment('Private — never shown publicly');
            $table->string('status', 16)->default('pending')->index()->comment('pending | published | hidden');
            $table->unsignedInteger('position')->default(0)->comment('Landing order (lower first)');
            $table->string('source', 16)->default('public')->comment('public | admin');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_testimonials');
    }
};
