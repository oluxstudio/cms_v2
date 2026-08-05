<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // actor; null = system

            // What type of entity was affected and which record
            $table->string('entity_type');      // page | form | form_response | todo | media | member | component
            $table->unsignedBigInteger('entity_id')->nullable(); // ID of the affected record

            // What happened
            $table->string('action');           // created | updated | published | unpublished | deleted | responded | completed | joined | uploaded

            // Human-readable content shown in activity feed
            $table->string('title');            // e.g. "Page "About Us" was published"
            $table->text('description')->nullable(); // extra detail / snippet

            // Deep-link to the entity in the admin (relative to site slug, e.g. /pages, /forms/5/responses)
            $table->string('url')->nullable();

            // Visual hint for icon rendering (same as entity_type, but can be overridden)
            $table->string('icon')->nullable(); // page | form | response | todo | media | member

            // Arbitrary extra payload (e.g. form name for a response, previous status for todos)
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'entity_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_activity_logs');
    }
};
