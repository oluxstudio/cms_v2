<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site installed templates. A row is a template a site has installed from the
 * Marketplace — either a built-in (references a TemplateRegistry key) or a custom
 * one imported from a .zip (its theme + pages live in `payload`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('builtin');   // builtin | custom
            $table->string('builtin_key')->nullable();       // set when source = builtin
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('gradient_class')->nullable();
            $table->json('payload')->nullable();             // custom: { theme: {...}, pages: [...] }
            $table->string('thumbnail_path')->nullable();    // public-relative path to a screenshot
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_templates');
    }
};
