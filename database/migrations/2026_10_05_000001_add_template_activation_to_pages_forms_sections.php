<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template-owned structure is ACTIVE only while its template is the site's
 * current one; it is never deleted (switching back brings it back).
 *   template_keys    the templates that declare this page / form / section
 *                    (null or [] = the owner's own — always active)
 *   template_active  false while none of those templates is the current one
 * Site DATA (collections, entries, posts, submissions) never carries this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('template_keys')->nullable()->after('site_template_id');
            $table->boolean('template_active')->default(true)->after('template_keys')->index();
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->json('template_keys')->nullable()->after('is_active');
            $table->boolean('template_active')->default(true)->after('template_keys');
        });
        Schema::table('page_component', function (Blueprint $table) {
            $table->json('template_keys')->nullable()->after('settings');
            $table->boolean('active')->default(true)->after('template_keys');
        });
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn(['template_keys', 'template_active']));
        Schema::table('forms', fn (Blueprint $t) => $t->dropColumn(['template_keys', 'template_active']));
        Schema::table('page_component', fn (Blueprint $t) => $t->dropColumn(['template_keys', 'active']));
    }
};
