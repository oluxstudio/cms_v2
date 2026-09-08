<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant knowledge base: chunked site content + optional embedding vector.
        Schema::create('ai_chunks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->string('source_type', 32);   // page | component | service | product | form | collection | business
            $table->string('source_id', 64);
            $table->unsignedSmallInteger('chunk_no')->default(0);
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('content_hash', 40);
            $table->timestamps();
            $table->unique(['site_id', 'source_type', 'source_id', 'chunk_no'], 'ai_chunks_source_unique');
        });

        // Per-tenant AI spend: one row per assistant turn.
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('user_id')->nullable();
            $table->string('driver', 24);
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedSmallInteger('tool_calls')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chunks');
        Schema::dropIfExists('ai_usage');
    }
};
