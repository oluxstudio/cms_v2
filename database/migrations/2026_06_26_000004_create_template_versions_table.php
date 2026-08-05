<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, versioned payload for a catalog template. Installs pin a version, so a
 * creator updating a template never breaks dependent sites.
 *
 * payload = { theme:{…}, fonts:{…}, css:"…", pages:[…] } — everything the installer
 * needs (page asset URLs are already absolute, pointing at the template's disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('version')->default('1.0.0');
            $table->json('manifest')->nullable();
            $table->json('payload');
            $table->string('status')->default('published'); // draft|in_review|published|rejected
            $table->timestamps();

            $table->unique(['template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
