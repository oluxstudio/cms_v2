<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            /**
             * URL-safe slug that identifies the form type, e.g. "contact", "newsletter",
             * "job-application". Set from the {formName} path segment in the API route.
             */
            $table->string('name');

            /**
             * Optional human-readable title derived from the slug on first submission,
             * e.g. "Contact" or "Job Application". Can be edited later.
             */
            $table->string('title')->nullable();

            $table->timestamps();

            // A site can only have one form per name
            $table->unique(['site_id', 'name']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
