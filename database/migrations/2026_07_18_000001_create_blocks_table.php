<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BlockKit — the jigsaw block tree. One table, one row per block; a page's
 * tree is a single ordered query assembled in PHP (nested set is overkill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->string('id', 24)->primary();                  // blk_xxxxxxxxxx
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('parent_id', 24)->nullable()->index(); // null = page root
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 32);
            $table->json('props')->nullable();
            $table->json('style')->nullable();
            $table->json('meta')->nullable();                     // { label, locked }
            $table->timestamps();

            $table->index(['page_id', 'parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
