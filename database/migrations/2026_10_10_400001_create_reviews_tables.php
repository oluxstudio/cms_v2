<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews & Testimonials module: customer reviews (collected on the site, by
 * an emailed request link, added by hand or imported) and the review requests
 * that carry the one-review-per-link token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('contact_id')->nullable()->index();
            $table->string('name', 120)->nullable();
            $table->string('email', 190);
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'email']);
            $table->index(['completed_at', 'reminder_sent_at', 'sent_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('request_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('email', 190)->nullable();          // private — never served
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->string('photo', 500)->nullable();          // "@media/…" ref
            $table->string('status', 20)->default('pending');  // pending | published | hidden
            $table->string('source', 20)->default('on_site');  // on_site | request | manual | import
            $table->boolean('featured')->default(false);
            $table->text('reply_body')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_requests');
    }
};
