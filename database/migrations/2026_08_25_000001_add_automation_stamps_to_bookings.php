<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Send-once stamps for the booking automations (reminder / review / rebook).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamp('rebook_prompted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'review_requested_at', 'rebook_prompted_at']);
        });
    }
};
