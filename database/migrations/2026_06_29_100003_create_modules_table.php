<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site declarative modules — AI/admin-created capabilities backed by a Collection.
 * The ModuleRegistry merges these with built-in (config) modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
            $table->json('schema')->nullable();        // snapshot of the field definitions
            $table->json('capabilities')->nullable();  // { list, get, submit }
            $table->json('frontend')->nullable();      // { block, variant, title }
            $table->json('intents')->nullable();       // keywords for LLM routing
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
