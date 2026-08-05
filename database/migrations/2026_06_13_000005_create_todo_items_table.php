<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Checkable sub-list items belonging to a todo.
        Schema::create('todo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('done')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['todo_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_items');
    }
};
