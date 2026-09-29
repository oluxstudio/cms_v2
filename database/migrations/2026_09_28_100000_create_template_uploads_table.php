<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 40)->unique();
            $table->string('name')->nullable();
            $table->string('original_filename')->nullable();
            // queued → scanning → building → ready | failed
            $table->string('status', 20)->default('queued')->index();
            $table->string('step')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('lint_score')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignUlid('template_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('build_started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_uploads');
    }
};
