<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BlockKit layouts — the page skeleton a BlockKit page renders inside:
 * rows of keyed regions (12-col widths), fixed regions carrying layout-owned
 * blocks (global header/footer) and editable regions receiving the page's own
 * block tree. The built-in "Blank" layout (is_system) always exists, cannot
 * be deleted, and is the default for pages with no explicit assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('structure');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('block_layout_id')->nullable()->after('layout_id')
                ->constrained('block_layouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('block_layout_id');
        });
        Schema::dropIfExists('block_layouts');
    }
};
