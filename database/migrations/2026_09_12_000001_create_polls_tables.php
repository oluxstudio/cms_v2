<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Polls module: question + options, votes deduped per visitor. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->string('question', 200);
            $table->string('slug', 220);
            $table->boolean('is_open')->default(true);
            $table->boolean('multiple')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('poll_id')->index();
            $table->string('label', 160);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('poll_id')->index();
            $table->foreignUlid('poll_option_id')->index();
            $table->string('voter_hash', 64)->index();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['poll_id', 'poll_option_id', 'voter_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
