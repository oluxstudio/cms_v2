<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();          // creator
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();  // assignee
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');   // open | done
            $table->string('priority')->default('normal'); // low | normal | high
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
