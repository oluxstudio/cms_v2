<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grow Collections from a metadata stub into the declarative-module data store:
 * a JSON `fields` schema (same shape as Forms) + per-site slug + public flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->json('fields')->nullable()->after('description');
            $table->boolean('is_public')->default(true)->after('fields');   // entries listable publicly
            $table->boolean('allow_submit')->default(true)->after('is_public'); // visitors may submit
        });

        // Backfill slugs for any existing rows, then enforce uniqueness per site.
        foreach (\DB::table('collections')->whereNull('slug')->get() as $row) {
            \DB::table('collections')->where('id', $row->id)
                ->update(['slug' => \Illuminate\Support\Str::slug($row->name) ?: ('collection-' . $row->id)]);
        }

        Schema::table('collections', function (Blueprint $table) {
            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'slug']);
            $table->dropColumn(['slug', 'fields', 'is_public', 'allow_submit']);
        });
    }
};
