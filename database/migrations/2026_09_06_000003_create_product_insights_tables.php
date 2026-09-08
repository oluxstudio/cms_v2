<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('product_id');
            $table->integer('delta');
            $table->integer('stock_after');
            $table->string('reason', 32); // sale | restock | manual | cancel_restock
            $table->foreignUlid('order_id')->nullable();
            $table->foreignUlid('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id');
            $table->foreignUlid('product_id');
            $table->string('event', 24); // view | add_to_cart
            $table->string('session_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_id', 'event', 'created_at']);
            $table->index(['site_id', 'event', 'created_at']);
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('product_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('body');
            $table->string('status', 16)->default('pending'); // pending | approved
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('reviews_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_events');
        Schema::dropIfExists('product_reviews');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('reviews_enabled'));
    }
};
