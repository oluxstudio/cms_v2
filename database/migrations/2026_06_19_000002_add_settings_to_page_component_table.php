<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_component', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('page_component', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
