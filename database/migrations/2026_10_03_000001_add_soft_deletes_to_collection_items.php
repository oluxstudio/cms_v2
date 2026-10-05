<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a collection entry records when (deleted_at) instead of removing
 * the row: it disappears everywhere, but can be restored from the
 * collection's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['collection_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropIndex(['collection_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
