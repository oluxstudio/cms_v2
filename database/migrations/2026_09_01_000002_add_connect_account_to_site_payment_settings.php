<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('site_payment_settings', 'connect_account_id')) {
            Schema::table('site_payment_settings', function (Blueprint $table) {
                $table->string('connect_account_id')->nullable()->after('enabled');
                $table->boolean('connect_charges_enabled')->default(false)->after('connect_account_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('site_payment_settings', fn (Blueprint $table) => $table->dropColumn(['connect_account_id', 'connect_charges_enabled']));
    }
};
