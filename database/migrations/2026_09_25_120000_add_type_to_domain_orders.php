<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            // register | renew — renewals reuse the same checkout + fulfilment pipeline.
            $table->string('type', 12)->default('register')->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
