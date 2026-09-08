<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('order_id')->index();
            $table->string('status', 24);
            $table->string('note')->nullable();
            $table->foreignUlid('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('delivered_at');
            $table->timestamp('refunded_at')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['returned_at', 'refunded_at']));
    }
};
