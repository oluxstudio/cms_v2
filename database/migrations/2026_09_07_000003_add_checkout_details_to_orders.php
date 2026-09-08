<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 20)->nullable()->after('id');
            $table->string('fulfilment', 16)->default('delivery')->after('shipping_address');
            $table->text('delivery_notes')->nullable()->after('fulfilment');
            $table->integer('vat_bp')->default(0)->after('total_cents');
            $table->integer('vat_cents')->default(0)->after('vat_bp');
            $table->boolean('marketing_consent')->default(false)->after('delivery_notes');
            $table->index(['site_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'order_number']);
            $table->dropColumn(['order_number', 'fulfilment', 'delivery_notes', 'vat_bp', 'vat_cents', 'marketing_consent']);
        });
    }
};
