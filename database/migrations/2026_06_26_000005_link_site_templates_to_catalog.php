<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a per-site install to the catalog entry + pinned version it came from.
 * (Legacy installs keep using builtin_key / payload; both paths are supported.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('site_id')
                ->constrained('templates')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->after('template_id')
                ->constrained('template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_templates', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['template_version_id']);
            $table->dropColumn(['template_id', 'template_version_id']);
        });
    }
};
