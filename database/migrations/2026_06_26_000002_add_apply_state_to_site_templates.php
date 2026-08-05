<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track whether an installed template is currently "in use" on the site, and snapshot
 * the theme it replaced so unapply can restore the site's previous theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('thumbnail_path');
            $table->json('previous_theme')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->dropColumn(['applied_at', 'previous_theme']);
        });
    }
};
