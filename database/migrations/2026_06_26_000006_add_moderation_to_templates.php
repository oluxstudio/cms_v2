<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moderation fields for the creator-publishing lifecycle:
 * draft → in_review (submitted_at) → published | rejected (rejection_reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('published_at');
            $table->text('rejection_reason')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'rejection_reason']);
        });
    }
};
