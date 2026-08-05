<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BlockKit undo/redo — one row per mutation batch (a drag action, an inspector
 * save, or a whole AI turn). `forward` and `inverse` hold the same normalized
 * op language, so undo applies inverse (reversed) and redo replays forward.
 * Undone rows form a suffix of the history; any new mutation deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('source', 8)->default('user');   // user | ai
            $table->string('label', 120);
            $table->json('forward');
            $table->json('inverse');
            $table->boolean('undone')->default(false);
            $table->timestamps();

            $table->index(['page_id', 'undone', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_batches');
    }
};
