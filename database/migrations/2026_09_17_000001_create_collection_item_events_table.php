<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Visitor engagement beacons for collection items (media views/plays). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_item_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('collection_id')->index();
            $table->foreignUlid('collection_item_id');
            $table->string('event', 24);
            $table->string('session_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['collection_item_id', 'event', 'created_at']);
            $table->index(['site_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_item_events');
    }
};
