<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide messages from the super admin: a banner across the app
        // (and optionally a copy in each site's notifications).
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 140);
            $table->text('body')->nullable();
            $table->string('level', 12)->default('info'); // info | warning | critical
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('audience', 12)->default('all'); // all | plans | accounts
            $table->json('plans')->nullable();
            $table->json('account_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('dismissible')->default(true);
            $table->boolean('add_to_alerts')->default(false);
            $table->timestamp('alerts_sent_at')->nullable();
            $table->string('created_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('announcement_id', 26);
            $table->string('user_id', 26);
            $table->timestamp('created_at')->nullable();
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
