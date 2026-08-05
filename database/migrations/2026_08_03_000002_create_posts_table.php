<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();      // author (account member)
            $table->string('title', 180);
            $table->string('slug', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->string('status', 12)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            // Simple engagement counters (bumped by the public site / API later).
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
