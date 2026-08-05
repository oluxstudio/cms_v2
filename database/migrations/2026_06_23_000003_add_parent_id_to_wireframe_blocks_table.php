<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            // Self-reference for nested blocks: a child block sits inside a
            // container/parent block. Null = top-level block in the wireframe.
            $table->foreignId('parent_id')->nullable()->after('wireframe_id')
                ->constrained('wireframe_blocks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
