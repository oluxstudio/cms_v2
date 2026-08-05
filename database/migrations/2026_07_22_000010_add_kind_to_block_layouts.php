<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A "component" is a user-built reusable block: same block-tree machinery as a
// layout, but no content_slot — it is stamped INTO pages instead of wrapping them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_layouts', function (Blueprint $table) {
            $table->string('kind', 20)->default('layout')->index()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('block_layouts', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
