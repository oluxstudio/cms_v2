<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per template: which of its collections RESET when a site uses it (start
 * empty — the site's own data, e.g. Bible Studies, Sermons, Events). Every
 * other collection loads with the template's entries, as in the preview.
 * Set in Admin › Templates › Edit. null/[] = nothing resets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('reset_collections')->nullable()->after('required_features');
        });

        // The church templates: these are each church's own content.
        DB::table('templates')->whereIn('builtin_key', ['graceway', 'u-bnzuuu1xff'])
            ->update(['reset_collections' => json_encode(['Bible Studies', 'Sermons', 'Events', 'Songs', 'Leadership'])]);
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('reset_collections'));
    }
};
