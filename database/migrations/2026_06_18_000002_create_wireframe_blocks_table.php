<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wireframe_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wireframe_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['wireframe_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wireframe_blocks');
    }
};
