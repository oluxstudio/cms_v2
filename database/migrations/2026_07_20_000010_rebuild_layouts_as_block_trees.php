<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clean rebuild of the layout system: a layout IS a block tree it owns, with
 * exactly one `content_slot` placeholder where each page's content splices in.
 * Regions, presets, structure JSON and shadow pages are all removed.
 *
 *   blocks: owned by a page (page_id) XOR a layout (layout_id).
 *   pages.block_layout_id → layouts; NULL resolves to the undeletable Blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Blocks gain a second (mutually exclusive) owner: a layout.
        Schema::table('blocks', function (Blueprint $table) {
            $table->foreignId('layout_id')->nullable()->after('page_id')
                ->constrained('block_layouts')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE blocks MODIFY page_id BIGINT UNSIGNED NULL');

        // History batches can belong to layout editing sessions too.
        Schema::table('block_batches', function (Blueprint $table) {
            $table->foreignId('layout_id')->nullable()->after('page_id')
                ->constrained('block_layouts')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE block_batches MODIFY page_id BIGINT UNSIGNED NULL');

        // The old region model is gone: drop structure, wipe stale rows, remove
        // shadow pages (their blocks cascade), detach pages for a fresh start.
        DB::table('pages')->whereNotNull('block_layout_id')->update(['block_layout_id' => null]);
        DB::table('pages')->where('url', 'like', '/\_layout-%')->delete();
        DB::table('block_layouts')->delete();
        Schema::table('block_layouts', function (Blueprint $table) {
            $table->dropColumn('structure');
        });
    }

    public function down(): void
    {
        Schema::table('block_layouts', function (Blueprint $table) {
            $table->json('structure')->nullable();
        });
        Schema::table('block_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });
    }
};
