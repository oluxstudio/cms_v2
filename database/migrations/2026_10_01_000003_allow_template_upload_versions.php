<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A template's new versions are uploads that reuse its key (same app, next
 * version), so one key can have several upload rows — one per build. Builds
 * of the same key never overlap (checked before queueing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            $table->dropIndex(['key']);
            $table->unique('key');
        });
    }
};
