<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template submissions — Nuxt apps found in the staging folder awaiting
     * moderator review. `extraction` holds the TemplateExtractor manifest
     * (pages, blocks, nodes, theme, fonts, behaviours, assets).
     */
    public function up(): void
    {
        Schema::create('template_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // staging folder name, e.g. "tekstack"
            $table->string('name');                   // display name
            $table->string('status')->default('pending'); // pending | accepted | rejected
            $table->json('extraction')->nullable();   // extractor manifest
            $table->text('note')->nullable();         // moderator note (esp. on reject)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_submissions');
    }
};
