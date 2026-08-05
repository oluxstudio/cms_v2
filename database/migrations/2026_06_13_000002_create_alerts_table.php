<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // System/notification alerts: milestones, new team member, task complete, etc.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // recipient: null = the whole team; set = a single user.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('level')->default('info');     // success | error | warning | info
            $table->string('type')->default('system');    // milestone | user_added | task_complete | system
            $table->string('audience')->default('all');   // RBAC: all | admins
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();           // optional deep-link inside the admin
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
