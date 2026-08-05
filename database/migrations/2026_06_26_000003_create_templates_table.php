<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The global template catalog. One row per published template (built-in seeds today,
 * user-created later). Replaces the filesystem registry as the marketplace's source
 * of truth so it can be searched, filtered, paginated and priced at scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // creator (null = system)
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('status')->default('published');     // draft|in_review|published|rejected|unlisted|suspended
            $table->unsignedInteger('price_cents')->default(0); // 0 = free
            $table->char('currency', 3)->default('usd');
            $table->string('source')->default('builtin');       // builtin|custom
            $table->string('builtin_key')->nullable();          // links to a preview demo / registry seed
            $table->string('accent_color')->nullable();
            $table->string('gradient_class')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('latest_version_id')->nullable();
            $table->unsignedInteger('installs_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['status', 'price_cents']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
