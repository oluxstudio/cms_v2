<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of what applying a template displaced (archived pages, the prior default
 * layout) so un-applying can restore the site's own content exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->json('previous_state')->nullable()->after('previous_theme');
        });
    }

    public function down(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->dropColumn('previous_state');
        });
    }
};
