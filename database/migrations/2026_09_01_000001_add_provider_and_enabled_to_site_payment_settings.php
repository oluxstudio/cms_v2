<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('site_payment_settings', 'enabled')) {
            Schema::table('site_payment_settings', function (Blueprint $table) {
                $table->string('provider', 32)->default('stripe')->after('site_id');
                $table->boolean('enabled')->default(false)->after('provider');
            });
            // Grandfather sites already taking payments: keys present = keep ON
            // (user-approved). Only NEW sites start with payments off.
            DB::table('site_payment_settings')
                ->whereNotNull('stripe_secret')->where('stripe_secret', '!=', '')
                ->whereNotNull('stripe_publishable')->where('stripe_publishable', '!=', '')
                ->update(['enabled' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('site_payment_settings', fn (Blueprint $table) => $table->dropColumn(['provider', 'enabled']));
    }
};
