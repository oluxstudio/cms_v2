<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records for a Collection — one row per submitted/created entry, stored as a JSON
 * `data` blob keyed by the collection's field keys (mirrors form_responses.fields).
 * A single shared table for all collections → no per-site/runtime DDL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete(); // denormalized for tenant scoping
            $table->json('data');
            $table->string('status')->default('published'); // published | draft
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'status']);
            $table->index(['site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
