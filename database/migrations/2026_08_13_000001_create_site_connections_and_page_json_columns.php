<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Connect — Stage 1 (the contract).
 *
 * `site_connections`: one row per site describing its Site Connect state — the
 * delivery mode (collect vs hydrate), the CORS/crawl domain allow-list, and
 * ingest/publish timestamps. The token itself is NOT stored here; Site Connect
 * reuses the existing `api_tokens` model (hashed, ability-scoped) — a connection
 * just records the tenant-level wiring.
 *
 * Plus per-page bookkeeping for the generated `page.json`: the monotonically
 * increasing `version` (cache-bust key baked into the JSON + exported HTML), the
 * last generation time, and the disk path it was written to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            // The CMS decides the mode; connect.js asks for it via /connect/status.
            $table->string('mode')->default('collect'); // collect | hydrate
            // Per-site allow-list for CORS on /connect/* and page-JSON reads, and
            // the SSRF allow-list for the crawler. e.g. ["riversidesalon.co.uk"].
            $table->json('allowed_origins')->nullable();
            $table->timestamp('last_ingested_at')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->unique('site_id'); // one connection per site
        });

        Schema::table('pages', function (Blueprint $table) {
            // Monotonic per-page version — the `version` field in page.json and
            // the `data-olx-version` baked into exported HTML. Bumped on publish.
            $table->unsignedInteger('page_json_version')->default(0)->after('is_published');
            $table->timestamp('page_json_generated_at')->nullable()->after('page_json_version');
            // Disk-relative path the JSON was last written to (tenant-prefixed).
            $table->string('page_json_path')->nullable()->after('page_json_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['page_json_version', 'page_json_generated_at', 'page_json_path']);
        });
        Schema::dropIfExists('site_connections');
    }
};
