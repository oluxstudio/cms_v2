<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scheduled visibility rules (date range · days/time · content · promo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->json('visibility')->nullable();
        });
        Schema::table('collections', function (Blueprint $table) {
            $table->json('visibility')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('components', fn (Blueprint $table) => $table->dropColumn('visibility'));
        Schema::table('collections', fn (Blueprint $table) => $table->dropColumn('visibility'));
    }
};
