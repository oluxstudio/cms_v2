<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Explicit ordering for collection items (drag/move in the Connect editor).
// Backfills sequentially per collection in created_at order so existing
// content keeps its current visual order.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('data');
        });

        foreach (DB::table('collection_items')->select('collection_id')->distinct()->pluck('collection_id') as $cid) {
            $i = 0;
            foreach (DB::table('collection_items')->where('collection_id', $cid)->orderBy('created_at')->orderBy('id')->pluck('id') as $id) {
                DB::table('collection_items')->where('id', $id)->update(['position' => $i++]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
