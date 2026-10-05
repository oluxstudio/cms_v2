<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-block collection query: which items a component shows from each
 * collection it reads (how many, sort, search, filter) — keyed by collection id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->json('collection_queries')->nullable()->after('collection_order');
        });
    }

    public function down(): void
    {
        Schema::table('components', fn (Blueprint $table) => $table->dropColumn('collection_queries'));
    }
};
