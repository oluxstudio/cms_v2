<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag pages / components / wireframes with the installed template that created them,
 * so a template can be cleanly UN-applied (remove exactly what it added). Null =
 * the site's own/default content, which always survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('site_template_id')->nullable()->after('layout_id')
                ->constrained('site_templates')->nullOnDelete();
        });
        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('site_template_id')->nullable()
                ->constrained('site_templates')->nullOnDelete();
        });
        Schema::table('wireframes', function (Blueprint $table) {
            $table->foreignId('site_template_id')->nullable()
                ->constrained('site_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['site_template_id']);
            $table->dropColumn('site_template_id');
        });
        Schema::table('components', function (Blueprint $table) {
            $table->dropForeign(['site_template_id']);
            $table->dropColumn('site_template_id');
        });
        Schema::table('wireframes', function (Blueprint $table) {
            $table->dropForeign(['site_template_id']);
            $table->dropColumn('site_template_id');
        });
    }
};
