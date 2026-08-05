<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Star ratings/reviews for templates (one per user per template), plus denormalised
 * aggregates on `templates` for fast sorting/display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0)->after('installs_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });

        Schema::create('template_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars'); // 1..5
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_ratings');
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
    }
};
