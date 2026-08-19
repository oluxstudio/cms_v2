<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);   // component | collection | form | post
            $table->char('subject_id', 26);
            $table->json('payload');
            $table->string('label', 120)->nullable();
            $table->string('created_by', 120)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'subject_type', 'subject_id', 'created_at'], 'content_versions_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
