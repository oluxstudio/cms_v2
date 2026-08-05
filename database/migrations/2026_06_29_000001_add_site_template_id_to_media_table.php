<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Tags media rows created by a template install, so unapply can remove
            // exactly those without touching the user's own uploads.
            $table->foreignId('site_template_id')->nullable()->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('site_template_id');
        });
    }
};
