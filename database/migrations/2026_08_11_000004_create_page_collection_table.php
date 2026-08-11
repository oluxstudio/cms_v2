<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_collection', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('page_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('collection_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'collection_id']);
            $table->index(['page_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_collection');
    }
};
