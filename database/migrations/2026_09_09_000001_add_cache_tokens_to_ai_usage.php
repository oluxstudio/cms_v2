<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prompt-cache accounting: how much of each turn's input was cached vs fresh. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->unsignedInteger('cache_creation_tokens')->nullable()->after('output_tokens');
            $table->unsignedInteger('cache_read_tokens')->nullable()->after('cache_creation_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropColumn(['cache_creation_tokens', 'cache_read_tokens']);
        });
    }
};
