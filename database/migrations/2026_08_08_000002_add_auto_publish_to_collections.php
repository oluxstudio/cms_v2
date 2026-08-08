<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public submissions are held for review by default; auto_publish restores
 * the instant-publish behavior per collection. Existing collections keep
 * auto-publishing (true) so live client sites don't change behavior
 * unannounced — NEW collections default to pending review.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('collections', 'auto_publish')) {
            Schema::table('collections', function (Blueprint $table) {
                $table->boolean('auto_publish')->default(false)->after('allow_submit');
            });
            \Illuminate\Support\Facades\DB::table('collections')->update(['auto_publish' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('collections', fn (Blueprint $table) => $table->dropColumn('auto_publish'));
    }
};
