<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('shipping_address')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('public_token', 40)->nullable()->unique()->after('stripe_payment_intent');
            $table->string('courier_email')->nullable()->after('public_token');
            $table->string('courier_token', 40)->nullable()->unique()->after('courier_email');
            $table->timestamp('courier_invited_at')->nullable()->after('courier_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'shipping_address', 'customer_phone', 'public_token', 'courier_email', 'courier_token', 'courier_invited_at',
        ]));
    }
};
