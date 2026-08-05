<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-DATE custom opening hours: a day-level booking_blocks row
 * (start_time NULL) with open/close_time set means "open, but only
 * within these hours" instead of "whole day off".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_blocks', 'open_time')) {
            Schema::table('booking_blocks', function (Blueprint $table) {
                $table->time('open_time')->nullable()->after('start_time');
                $table->time('close_time')->nullable()->after('open_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_blocks', 'open_time')) {
            Schema::table('booking_blocks', fn (Blueprint $t) => $t->dropColumn(['open_time', 'close_time']));
        }
    }
};
