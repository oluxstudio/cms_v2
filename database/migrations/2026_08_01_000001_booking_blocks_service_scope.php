<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability exceptions become per-service scopable: service_id NULL keeps
 * the old meaning (site-wide block), a set id blocks only that service.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_blocks', 'service_id')) {
            Schema::table('booking_blocks', function (Blueprint $table) {
                $table->foreignId('service_id')->nullable()->after('site_id')
                    ->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_blocks', 'service_id')) {
            Schema::table('booking_blocks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_id');
            });
        }
    }
};
