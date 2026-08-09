<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account-level audit trail — the events shown on Settings → Activity & Logs:
 * logins, password/profile changes, sites created, staff invited/joined, roles
 * created, API keys issued/revoked and API write calls. Distinct from
 * site_activity_logs (which is per-site content activity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_activity_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('account_id')->index();          // whose account the event belongs to
            $table->ulid('actor_id')->nullable();          // who performed it (may differ / be null)
            $table->string('action');                       // login | password_changed | member_invited | ...
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('category')->default('general'); // Login | Security | Team | Sites | API | Profile
            $table->string('icon')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activity_logs');
    }
};
