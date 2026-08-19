<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Connect — Stage 3 staging model.
 *
 * Ingestion is a two-step: raw pages land in `page_ingestions`, get split into
 * `ingested_sections` (each classified with a confidence), then a human (or an
 * auto-commit for high-confidence pages) materialises them into the real
 * Component/Collection/Post/Form models. Keeping a staging layer means low-
 * confidence guesses go to a review queue instead of silently polluting content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_ingestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            // The CMS page this ingestion resolves to (created on commit).
            $table->foreignUlid('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url');
            $table->longText('raw_html')->nullable();   // sanitised snapshot
            $table->longText('styles')->nullable();      // same-origin CSS text
            $table->json('meta')->nullable();            // title, description, og, etc.
            $table->json('discovered_links')->nullable(); // internal links for the crawl queue
            // received → extracting → classified → committed | failed
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('ingested_sections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('page_ingestion_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete(); // denormalised tenant scope
            $table->unsignedInteger('position')->default(0);
            $table->string('tag')->nullable();           // section/header/footer/nav/main child
            $table->longText('html')->nullable();        // the section's sanitised HTML (preview fidelity)
            $table->longText('css')->nullable();         // matched CSS rules
            // component | collection | post | form
            $table->string('classification')->default('component');
            $table->decimal('confidence', 4, 3)->default(0);
            $table->boolean('needs_review')->default(false);
            $table->json('fields')->nullable();          // extracted fields / item schema+rows / form fields
            // Where it was materialised to (set on commit).
            $table->string('committed_ref_type')->nullable(); // component|collection|post|form
            $table->string('committed_ref_id')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'classification']);
            $table->index(['page_ingestion_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingested_sections');
        Schema::dropIfExists('page_ingestions');
    }
};
